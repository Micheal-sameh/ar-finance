<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as SpreadsheetReaderException;

/**
 * Shared plumbing for form requests that import an uploaded .xlsx/.xls/.csv:
 * reading the sheet, mapping header aliases, trimming cells, and reporting
 * problems as one multi-line "file" error (Inertia keeps only the first
 * message per field, so one message per row would hide all but the first).
 */
trait ReadsSpreadsheetRows
{
    /**
     * @var string[]
     */
    private array $messages = [];

    /**
     * The uploaded sheet as header + data rows, or null (with a message
     * recorded) if it can't be read or is empty.
     *
     * @return array<int, array<int, mixed>>|null
     */
    private function readSheet(): ?array
    {
        try {
            $rows = IOFactory::load($this->file('file')->getRealPath())
                ->getActiveSheet()
                ->toArray(null, true, true, false);
        } catch (SpreadsheetReaderException) {
            $this->messages[] = 'This file could not be read. Upload a valid .xlsx, .xls, or .csv file.';

            return null;
        }

        if ($rows === []) {
            $this->messages[] = 'The file is empty.';

            return null;
        }

        return $rows;
    }

    /**
     * @param  array<int, mixed>  $header
     * @param  array<string, string>  $aliases  lowercase header label => field name
     * @return array<int, string> column index => field name (or the original label if unrecognised)
     */
    private function mapHeader(array $header, array $aliases): array
    {
        $columns = [];

        foreach ($header as $index => $label) {
            $key = strtolower(trim((string) $label));
            $columns[$index] = $aliases[$key] ?? $key;
        }

        return $columns;
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

    /**
     * @param  array<string, string>  $values
     */
    private function isBlankRow(array $values): bool
    {
        return implode('', $values) === '';
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
        return ! in_array(strtolower(trim($value)), ['no', 'false', '0', 'inactive', 'n'], true);
    }

    private function flushMessages(Validator $validator): void
    {
        if ($this->messages !== []) {
            $validator->errors()->add('file', implode("\n", $this->messages));
        }
    }
}
