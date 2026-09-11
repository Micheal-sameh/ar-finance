<?php

namespace App\Http\Requests\BankTransactions;

use App\DTOs\BankTransactionRowData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Accepts pasted CSV text — "date,description,amount" per line — rather
 * than a file upload, so "import" works without file-storage plumbing.
 * Parsing happens during validation (in withValidator()) so a malformed
 * line surfaces as a normal field error, not a 500.
 */
class ImportBankTransactionsRequest extends FormRequest
{
    /** @var BankTransactionRowData[] */
    private array $parsedRows = [];

    public function authorize(): bool
    {
        return $this->user()->can('bank_accounts.manage');
    }

    public function rules(): array
    {
        return [
            'csv' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('csv')) {
                return;
            }

            $lines = array_filter(array_map('trim', explode("\n", $this->input('csv', ''))), fn ($line) => $line !== '');

            foreach ($lines as $lineNumber => $line) {
                $columns = str_getcsv($line);

                if (count($columns) < 3) {
                    $validator->errors()->add('csv', 'Line '.($lineNumber + 1)." isn't valid CSV: expected date,description,amount.");

                    continue;
                }

                [$date, $description, $amount] = [trim($columns[0]), trim($columns[1]), trim($columns[2])];

                if (! strtotime($date) || ! is_numeric($amount)) {
                    $validator->errors()->add('csv', 'Line '.($lineNumber + 1).' has an invalid date or amount.');

                    continue;
                }

                $this->parsedRows[] = BankTransactionRowData::fromArray([
                    'date' => date('Y-m-d', strtotime($date)),
                    'description' => $description,
                    'amount' => $amount,
                ]);
            }

            if (! $validator->errors()->has('csv') && $this->parsedRows === []) {
                $validator->errors()->add('csv', 'No transactions found to import.');
            }
        });
    }

    /**
     * @return BankTransactionRowData[]
     */
    public function rows(): array
    {
        return $this->parsedRows;
    }
}
