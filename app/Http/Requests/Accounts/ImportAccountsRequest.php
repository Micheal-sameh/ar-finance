<?php

namespace App\Http\Requests\Accounts;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;

/**
 * Accepts an uploaded .xlsx/.xls/.csv file with one account per row.
 * Parsing and structural checks (are the required columns present, are
 * enum values recognisable, is the amount numeric) happen during
 * validation, in withValidator(), so a malformed file surfaces as a normal
 * field error instead of a 500. Business-rule checks that depend on other
 * rows or existing accounts (uniqueness, code/parent nesting) are left to
 * AccountService::import(), which has that context.
 */
class ImportAccountsRequest extends FormRequest
{
    private const HEADER_ALIASES = [
        'code' => 'code',
        'account_code' => 'code',
        'name' => 'name',
        'account_name' => 'name',
        'type' => 'type',
        'account_type' => 'type',
        'normal_balance' => 'normal_balance',
        'normal balance' => 'normal_balance',
        'parent_code' => 'parent_code',
        'parent code' => 'parent_code',
        'parent' => 'parent_code',
        'is_active' => 'is_active',
        'active' => 'is_active',
        'status' => 'is_active',
        'opening_balance' => 'opening_balance',
        'opening balance' => 'opening_balance',
    ];

    /** @var array<int, array{row: int, code: string, name: string, type: AccountType, normal_balance: ?NormalBalance, parent_code: ?string, is_active: bool, opening_balance: ?float}> */
    private array $parsedRows = [];

    /**
     * Collected during parsing and joined into a single message at the
     * end — Inertia's default error sharing keeps only the first message
     * per field, so adding one error at a time to the validator would hide
     * every row but the first.
     *
     * @var string[]
     */
    private array $messages = [];

    public function authorize(): bool
    {
        return $this->user()->can('accounts.create');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('file')) {
                return;
            }

            try {
                $sheetRows = IOFactory::load($this->file('file')->getRealPath())
                    ->getActiveSheet()
                    ->toArray(null, true, true, false);
            } catch (SpreadsheetReaderException) {
                $this->messages[] = 'This file could not be read. Upload a valid .xlsx, .xls, or .csv file.';
                $this->flushMessages($validator);

                return;
            }

            if ($sheetRows === []) {
                $this->messages[] = 'The file is empty.';
                $this->flushMessages($validator);

                return;
            }

            $columns = $this->mapHeader(array_shift($sheetRows));

            foreach (['code', 'name', 'type'] as $required) {
                if (! in_array($required, $columns, true)) {
                    $this->messages[] = "The file must have a \"{$required}\" column.";
                }
            }

            if ($this->messages !== []) {
                $this->flushMessages($validator);

                return;
            }

            foreach ($sheetRows as $index => $cells) {
                $rowNumber = $index + 2; // +1 for zero-based index, +1 for the header row.
                $values = $this->rowValues($columns, $cells);

                if ($this->isBlankRow($values)) {
                    continue;
                }

                $this->parseRow($rowNumber, $values);
            }

            if ($this->messages === [] && $this->parsedRows === []) {
                $this->messages[] = 'No accounts found to import.';
            }

            $this->flushMessages($validator);
        });
    }

    private function flushMessages(Validator $validator): void
    {
        if ($this->messages !== []) {
            $validator->errors()->add('file', implode("\n", $this->messages));
        }
    }

    /**
     * @param  array<int, string>  $header
     * @return array<int, string> column index => normalized field name (or the original header if unrecognised)
     */
    private function mapHeader(array $header): array
    {
        $normalized = [];

        foreach ($header as $index => $label) {
            $key = strtolower(trim((string) $label));
            $normalized[$index] = self::HEADER_ALIASES[$key] ?? $key;
        }

        return $normalized;
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, mixed>  $cells
     * @return array<string, string>
     */
    private function rowValues(array $columns, array $cells): array
    {
        $values = [];

        foreach ($columns as $index => $field) {
            $values[$field] = trim((string) ($cells[$index] ?? ''));
        }

        return $values;
    }

    private function isBlankRow(array $values): bool
    {
        return implode('', $values) === '';
    }

    private function parseRow(int $rowNumber, array $values): void
    {
        $code = $values['code'] ?? '';
        $name = $values['name'] ?? '';
        $typeInput = $values['type'] ?? '';

        if ($code === '' || $name === '' || $typeInput === '') {
            $this->messages[] = "Row {$rowNumber}: code, name, and type are required.";

            return;
        }

        $type = $this->matchEnum(AccountType::cases(), $typeInput, fn (AccountType $case) => $case->label());

        if (! $type) {
            $this->messages[] = "Row {$rowNumber}: \"{$typeInput}\" isn't a valid account type (asset, liability, equity, revenue, expense).";

            return;
        }

        $normalBalance = null;
        $normalBalanceInput = $values['normal_balance'] ?? '';

        if ($normalBalanceInput !== '') {
            $normalBalance = $this->matchEnum(NormalBalance::cases(), $normalBalanceInput, fn (NormalBalance $case) => $case->label());

            if (! $normalBalance) {
                $this->messages[] = "Row {$rowNumber}: \"{$normalBalanceInput}\" isn't a valid normal balance (debit, credit).";

                return;
            }
        }

        $openingBalance = null;
        $openingBalanceInput = $values['opening_balance'] ?? '';

        if ($openingBalanceInput !== '') {
            if (! is_numeric($openingBalanceInput)) {
                $this->messages[] = "Row {$rowNumber}: opening balance must be a number.";

                return;
            }

            $openingBalance = (float) $openingBalanceInput;
        }

        $this->parsedRows[] = [
            'row' => $rowNumber,
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'normal_balance' => $normalBalance,
            'parent_code' => ($values['parent_code'] ?? '') !== '' ? $values['parent_code'] : null,
            'is_active' => $this->parseBool($values['is_active'] ?? ''),
            'opening_balance' => $openingBalance,
        ];
    }

    /**
     * @template T of \UnitEnum
     *
     * @param  T[]  $cases
     * @param  callable(T): string  $label
     * @return T|null
     */
    private function matchEnum(array $cases, string $input, callable $label): mixed
    {
        $input = strtolower(trim($input));

        foreach ($cases as $case) {
            if (strtolower($case->value) === $input || strtolower($label($case)) === $input) {
                return $case;
            }
        }

        return null;
    }

    private function parseBool(string $value): bool
    {
        $value = strtolower(trim($value));

        return ! in_array($value, ['no', 'false', '0', 'inactive', 'n'], true);
    }

    /**
     * @return array<int, array{row: int, code: string, name: string, type: AccountType, normal_balance: ?NormalBalance, parent_code: ?string, is_active: bool, opening_balance: ?float}>
     */
    public function rows(): array
    {
        return $this->parsedRows;
    }
}
