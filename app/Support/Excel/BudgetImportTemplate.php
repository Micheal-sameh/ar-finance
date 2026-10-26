<?php

namespace App\Support\Excel;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The yearly budget workbook, in the layout the budget import reads: a
 * first row that explains the sheet, then a header row, then one row per
 * account with a column for each month. "Applies to" is a dropdown of the
 * cost center names. Used for both the blank starter template (one row per
 * revenue/expense account of the chart, so there is nothing to look up)
 * and the export of a saved budget.
 */
class BudgetImportTemplate
{
    private const HEADER = ['Code', 'Account', 'Applies to', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    /** Row 1 is the explanation, row 2 the column headings, data starts on row 3. */
    private const HEADER_ROW = 2;

    private const FIRST_DATA_ROW = 3;

    /** Extra rows that get the dropdown below the last filled row, for accounts added by hand. */
    private const SPARE_ROWS = 100;

    private const EXPLANATION = "HOW TO FILL THIS IN — a yearly budget: the amount you plan to earn (revenue accounts) or spend (expense accounts) in each month, one row per account.\n"
        ."• Code (required): the account code from your chart of accounts. Only revenue and expense accounts can be budgeted.\n"
        ."• Account: the account name, for reference only — the code decides the account.\n"
        ."• Applies to (optional): pick a cost center from the dropdown to budget one department or project; leave empty for the whole service. To budget the same account for two centers, add a second row with the same code.\n"
        ."• Jan – Dec (optional): the planned amount for each month — a number, zero or more, no currency symbol. Leave empty for none. Rows with every month empty are ignored.\n"
        .'• Year (export only): the total of the twelve months, for information only and ignored on import.';

    /**
     * @param  array<int, array{code: string, name: string, type: string}>  $accounts
     * @param  array<int, string>  $costCenterNames
     */
    public static function download(array $accounts, array $costCenterNames): StreamedResponse
    {
        $rows = array_map(fn (array $account) => [$account['code'], $account['name'], null], $accounts);

        return self::stream('budget-import-template.xlsx', self::build($rows, $costCenterNames, false));
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows  code, account, applies to, Jan–Dec, year total
     * @param  array<int, string>  $costCenterNames
     */
    public static function export(string $filename, array $rows, array $costCenterNames): StreamedResponse
    {
        return self::stream($filename, self::build($rows, $costCenterNames, true));
    }

    private static function stream(string $filename, Spreadsheet $spreadsheet): StreamedResponse
    {
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, string>  $costCenterNames
     */
    private static function build(array $rows, array $costCenterNames, bool $withYearTotal): Spreadsheet
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Budget');

        $header = $withYearTotal ? [...self::HEADER, 'Year'] : self::HEADER;
        $lastColumn = $withYearTotal ? 'P' : 'O';
        $headerRow = self::HEADER_ROW;

        self::addExplanationRow($sheet, $lastColumn);

        $sheet->fromArray($header, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:{$lastColumn}{$headerRow}")->getFont()->setBold(true);

        foreach ($rows as $index => $row) {
            $rowNumber = self::FIRST_DATA_ROW + $index;
            // Codes are text so Excel never turns 0100 into 100.
            $sheet->setCellValueExplicit("A{$rowNumber}", (string) $row[0], DataType::TYPE_STRING);
            $sheet->fromArray(array_slice($row, 1), null, "B{$rowNumber}");
        }

        foreach (range('A', $lastColumn) as $column) {
            // The merged explanation in A1 must not stretch column A.
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Keep the explanation and headings in view while scrolling.
        $sheet->freezePane('D'.self::FIRST_DATA_ROW);

        self::addCostCenterDropdown($spreadsheet, $sheet, $costCenterNames, self::FIRST_DATA_ROW + count($rows) - 1 + self::SPARE_ROWS);

        $notes = $spreadsheet->createSheet();
        $notes->setTitle('Instructions');
        $notes->fromArray([
            ['What this is', '', 'A yearly budget: the amount you plan to earn (revenue accounts) or spend (expense accounts) in each month, one row per account. The first row of the Budget sheet explains each column.'],
            ['', '', ''],
            ['Column', 'Required', 'Notes'],
            ['Code', 'Yes', 'The account code from your chart of accounts. Only revenue and expense accounts can be budgeted.'],
            ['Account', 'No', 'For your reference only — the code decides the account.'],
            ['Applies to', 'No', 'Pick a cost center from the dropdown. Leave empty for the whole service.'],
            ['Jan – Dec', 'No', 'The planned amount for each month, zero or more. Leave empty for none.'],
            ['', '', ''],
            ['Rows with every month empty are ignored. Importing only fills the budget page for review — nothing is saved until you click Save budget.', '', ''],
        ], null, 'A1');
        $notes->getStyle('A1')->getFont()->setBold(true);
        $notes->getStyle('A3:C3')->getFont()->setBold(true);

        foreach (range('A', 'C') as $column) {
            $notes->getColumnDimension($column)->setAutoSize(true);
        }

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    /**
     * Row 1: the explanation, merged across the table and wrapped, so the
     * sheet is understandable without any other documentation.
     */
    private static function addExplanationRow(Worksheet $sheet, string $lastColumn): void
    {
        $sheet->mergeCells("A1:{$lastColumn}1");
        $sheet->setCellValue('A1', self::EXPLANATION);

        $style = $sheet->getStyle('A1');
        $style->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFF8DC');
        $style->getFont()->setSize(10);

        $sheet->getRowDimension(1)->setRowHeight(118);
    }

    /**
     * Makes every "Applies to" cell a dropdown of the cost center names. The
     * names live on a hidden sheet because an inline list is capped at 255
     * characters; an empty cell stays allowed (the whole service).
     *
     * @param  array<int, string>  $costCenterNames
     */
    private static function addCostCenterDropdown(Spreadsheet $spreadsheet, Worksheet $sheet, array $costCenterNames, int $lastRow): void
    {
        if ($costCenterNames === []) {
            return;
        }

        $lists = $spreadsheet->createSheet();
        $lists->setTitle('Cost centers');
        $lists->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        foreach (array_values($costCenterNames) as $index => $name) {
            $lists->setCellValueExplicit('A'.($index + 1), $name, DataType::TYPE_STRING);
        }

        $source = "'Cost centers'!\$A\$1:\$A\$".count($costCenterNames);

        $validation = new DataValidation;
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        // PhpSpreadsheet writes this inverted: true = show the in-cell arrow.
        $validation->setShowDropDown(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorTitle('Unknown cost center');
        $validation->setError('Pick a cost center from the list, or leave the cell empty for the whole service.');
        $validation->setShowInputMessage(true);
        $validation->setPromptTitle('Applies to');
        $validation->setPrompt('Pick a cost center, or leave empty for the whole service.');
        $validation->setFormula1($source);

        $sheet->setDataValidation('C'.self::FIRST_DATA_ROW.":C{$lastRow}", $validation);
    }
}
