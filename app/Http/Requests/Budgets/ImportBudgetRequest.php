<?php

namespace App\Http\Requests\Budgets;

use App\Http\Requests\Concerns\ReadsSpreadsheetRows;
use App\Models\Budget;
use App\Models\BudgetLine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * An uploaded .xlsx/.xls/.csv with one budget line per row: the account
 * code, an optional cost center name, and an amount for each month. Only
 * structural checks happen here (a code on every row, numeric non-negative
 * months); matching codes and cost centers to real records is left to
 * BudgetService::resolveImport().
 */
class ImportBudgetRequest extends FormRequest
{
    use ReadsSpreadsheetRows;

    private const MONTH_NAMES = [
        1 => ['jan', 'january'], 2 => ['feb', 'february'], 3 => ['mar', 'march'], 4 => ['apr', 'april'],
        5 => ['may'], 6 => ['jun', 'june'], 7 => ['jul', 'july'], 8 => ['aug', 'august'],
        9 => ['sep', 'sept', 'september'], 10 => ['oct', 'october'], 11 => ['nov', 'november'], 12 => ['dec', 'december'],
    ];

    /** @var array<int, array{row: int, code: string, cost_center: ?string, months: array<int, float>}> */
    private array $parsedRows = [];

    public function authorize(): bool
    {
        return $this->user()->can('manage', Budget::class);
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

            $sheetRows = $this->readSheet();

            if ($sheetRows === null) {
                $this->flushMessages($validator);

                return;
            }

            // The template starts with an explanation row, so the headings are
            // the first row (within the first few) that has a "code" column.
            [$headerIndex, $columns] = $this->findHeader($sheetRows);

            if ($columns === null) {
                $this->messages[] = 'The file must have a "code" column with the account codes.';
            }

            if ($this->messages === []) {
                foreach (array_slice($sheetRows, $headerIndex + 1, null, true) as $index => $cells) {
                    $values = $this->rowValues($columns, $cells);

                    if (! $this->isBlankRow($values)) {
                        $this->parseRow($index + 1, $values); // +1: zero-based index to the row number in Excel.
                    }
                }

                if ($this->messages === [] && $this->parsedRows === []) {
                    $this->messages[] = 'No budget amounts found to import.';
                }
            }

            $this->flushMessages($validator);
        });
    }

    /**
     * @return array<int, array{row: int, code: string, cost_center: ?string, months: array<int, float>}>
     */
    public function rows(): array
    {
        return $this->parsedRows;
    }

    /**
     * @param  array<int, array<int, mixed>>  $sheetRows
     * @return array{0: int, 1: array<int, string>|null}
     */
    private function findHeader(array $sheetRows): array
    {
        foreach (array_slice($sheetRows, 0, 10, true) as $index => $cells) {
            $columns = $this->mapHeader($cells, $this->headerAliases());

            if (in_array('code', $columns, true)) {
                return [$index, $columns];
            }
        }

        return [0, null];
    }

    /**
     * @return array<string, string>
     */
    private function headerAliases(): array
    {
        $aliases = [
            'code' => 'code',
            'account code' => 'code',
            'account_code' => 'code',
            'applies to' => 'cost_center',
            'applies_to' => 'cost_center',
            'cost center' => 'cost_center',
            'cost_center' => 'cost_center',
            'center' => 'cost_center',
        ];

        foreach (self::MONTH_NAMES as $month => $names) {
            foreach ($names as $name) {
                $aliases[$name] = "m{$month}";
            }
        }

        return $aliases;
    }

    /**
     * @param  array<string, string>  $values
     */
    private function parseRow(int $rowNumber, array $values): void
    {
        $code = $values['code'] ?? '';

        if ($code === '') {
            $this->messages[] = "Row {$rowNumber}: the account code is required.";

            return;
        }

        $months = [];

        foreach (BudgetLine::MONTHS as $month) {
            $input = str_replace([',', ' '], '', $values["m{$month}"] ?? '');

            if ($input !== '' && (! is_numeric($input) || (float) $input < 0)) {
                $this->messages[] = "Row {$rowNumber}: the {$month}".$this->ordinal($month)." month must be a number, zero or more (got \"{$values["m{$month}"]}\").";

                return;
            }

            $months[$month] = round((float) $input, 2);
        }

        // A template row with every month left empty simply isn't budgeted.
        if (array_sum($months) === 0.0) {
            return;
        }

        $this->parsedRows[] = [
            'row' => $rowNumber,
            'code' => $code,
            'cost_center' => ($values['cost_center'] ?? '') !== '' ? $values['cost_center'] : null,
            'months' => $months,
        ];
    }

    private function ordinal(int $number): string
    {
        return match ($number) {
            1 => 'st', 2 => 'nd', 3 => 'rd', default => 'th',
        };
    }
}
