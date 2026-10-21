<?php

namespace App\Http\Requests\CostCenters;

use App\Enums\CostCenterType;
use App\Http\Requests\Concerns\ReadsSpreadsheetRows;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * An uploaded .xlsx/.xls/.csv with one cost/profit center per row. Only
 * structural checks happen here (required columns, recognisable type,
 * numeric budget); name uniqueness and parent resolution need the existing
 * centers and are left to CostCenterService::import().
 */
class ImportCostCentersRequest extends FormRequest
{
    use ReadsSpreadsheetRows;

    private const HEADER_ALIASES = [
        'name' => 'name',
        'center' => 'name',
        'center_name' => 'name',
        'type' => 'type',
        'center_type' => 'type',
        'parent' => 'parent',
        'parent_name' => 'parent',
        'parent center' => 'parent',
        'budget' => 'budget',
        'is_active' => 'is_active',
        'active' => 'is_active',
        'status' => 'is_active',
    ];

    /** @var array<int, array{row: int, name: string, type: CostCenterType, parent: ?string, budget: ?float, is_active: bool}> */
    private array $parsedRows = [];

    public function authorize(): bool
    {
        return $this->user()->can('cost_centers.manage');
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

            $columns = $this->mapHeader(array_shift($sheetRows), self::HEADER_ALIASES);

            foreach (['name', 'type'] as $required) {
                if (! in_array($required, $columns, true)) {
                    $this->messages[] = "The file must have a \"{$required}\" column.";
                }
            }

            if ($this->messages === []) {
                foreach ($sheetRows as $index => $cells) {
                    $values = $this->rowValues($columns, $cells);

                    if (! $this->isBlankRow($values)) {
                        $this->parseRow($index + 2, $values); // +1 zero-based, +1 header row.
                    }
                }

                if ($this->messages === [] && $this->parsedRows === []) {
                    $this->messages[] = 'No centers found to import.';
                }
            }

            $this->flushMessages($validator);
        });
    }

    /**
     * @param  array<string, string>  $values
     */
    private function parseRow(int $rowNumber, array $values): void
    {
        $name = $values['name'] ?? '';
        $typeInput = $values['type'] ?? '';

        if ($name === '' || $typeInput === '') {
            $this->messages[] = "Row {$rowNumber}: name and type are required.";

            return;
        }

        $type = $this->matchEnum(CostCenterType::cases(), $typeInput, fn (CostCenterType $case) => $case->label());

        if (! $type) {
            $this->messages[] = "Row {$rowNumber}: \"{$typeInput}\" is not a valid type (cost or profit).";

            return;
        }

        $budgetInput = $values['budget'] ?? '';

        if ($budgetInput !== '' && (! is_numeric($budgetInput) || (float) $budgetInput < 0)) {
            $this->messages[] = "Row {$rowNumber}: budget must be a number, zero or more.";

            return;
        }

        $this->parsedRows[] = [
            'row' => $rowNumber,
            'name' => $name,
            'type' => $type,
            'parent' => ($values['parent'] ?? '') !== '' ? $values['parent'] : null,
            'budget' => $budgetInput !== '' ? (float) $budgetInput : null,
            'is_active' => $this->parseBool($values['is_active'] ?? ''),
        ];
    }

    /**
     * @return array<int, array{row: int, name: string, type: CostCenterType, parent: ?string, budget: ?float, is_active: bool}>
     */
    public function rows(): array
    {
        return $this->parsedRows;
    }
}
