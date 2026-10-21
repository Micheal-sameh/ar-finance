<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The starter workbook for the cost/profit centers import: the columns
 * CostCenterService::import() understands, two obviously fake placeholder
 * rows (greyed out) and an Instructions sheet.
 */
class CostCenterImportTemplate
{
    public static function download(): StreamedResponse
    {
        $writer = new Xlsx(self::build());

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'cost-centers-import-template.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Centers');

        $sheet->fromArray(['name', 'type', 'parent', 'budget', 'is_active'], null, 'A1');
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);

        $sheet->fromArray([
            ['Example cost center (replace or delete)', 'cost', '', '', 'true'],
            ['Example profit center (replace or delete)', 'profit', '', '', 'true'],
        ], null, 'A2');
        $sheet->getStyle('A2:E3')->getFont()->setItalic(true)->getColor()->setRGB('9CA3AF');

        foreach (range('A', 'E') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Instructions');
        $notes->fromArray([
            ['Column', 'Required', 'Notes'],
            ['name', 'Yes', 'Unique center name.'],
            ['type', 'Yes', 'cost or profit.'],
            ['parent', 'No', 'Name of an existing center, or one anywhere in this file.'],
            ['budget', 'No', 'A number, zero or more. Leave blank for none.'],
            ['is_active', 'No', 'true or false — defaults to true.'],
            ['', '', ''],
            ['Delete the grey example rows on the Centers sheet before importing.', '', ''],
        ], null, 'A1');
        $notes->getStyle('A1:C1')->getFont()->setBold(true);

        foreach (range('A', 'C') as $column) {
            $notes->getColumnDimension($column)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
