<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The starter workbook for the accounts import: the columns
 * AccountService::import() understands, two obviously fake placeholder rows
 * (greyed out, no real codes or balances) and an Instructions sheet.
 */
class AccountImportTemplate
{
    private const FILENAME = 'accounts-import-template.xlsx';

    public static function download(): StreamedResponse
    {
        $writer = new Xlsx(self::build());

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, self::FILENAME, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private static function build(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Accounts');

        $sheet->fromArray(['code', 'name', 'type', 'normal_balance', 'parent_code', 'is_active', 'opening_balance'], null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);

        $sheet->fromArray([
            ['EX-1', 'Example parent account (replace or delete)', 'asset', 'debit', '', 'true', ''],
            ['EX-1-1', 'Example child account (replace or delete)', 'asset', 'debit', 'EX-1', 'true', ''],
        ], null, 'A2');
        $sheet->getStyle('A2:G3')->getFont()->setItalic(true)->getColor()->setRGB('9CA3AF');

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Instructions');
        $notes->fromArray([
            ['Column', 'Required', 'Notes'],
            ['code', 'Yes', 'Unique account code.'],
            ['name', 'Yes', 'Account name.'],
            ['type', 'Yes', 'asset, liability, equity, revenue or expense.'],
            ['normal_balance', 'No', 'debit or credit — defaults from the type.'],
            ['parent_code', 'No', 'Code of an existing account, or one earlier in the file.'],
            ['is_active', 'No', 'true or false — defaults to true.'],
            ['opening_balance', 'No', 'Leave blank for none.'],
            ['', '', ''],
            ['Delete the grey example rows on the Accounts sheet before importing.', '', ''],
        ], null, 'A1');
        $notes->getStyle('A1:C1')->getFont()->setBold(true);

        foreach (range('A', 'C') as $column) {
            $notes->getColumnDimension($column)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }
}
