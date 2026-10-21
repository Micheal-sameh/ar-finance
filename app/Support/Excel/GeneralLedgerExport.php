<?php

namespace App\Support\Excel;

use App\Models\Account;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Turns a general-ledger report into a downloadable sheet with only the
 * columns the user chose on the page.
 */
class GeneralLedgerExport
{
    /** @var array<string, string> line key => column heading, in display order */
    public const COLUMNS = [
        'date' => 'Date',
        'description' => 'Description',
        'reference' => 'Reference',
        'cost_center' => 'Center',
        'debit' => 'Debit',
        'credit' => 'Credit',
        'balance' => 'Balance',
    ];

    /**
     * @param  array{lines: array<int, array<string, mixed>>, ending_balance: float}  $ledger
     */
    public static function download(Account $account, array $ledger, ?string $requestedColumns): StreamedResponse
    {
        $columns = self::columns($requestedColumns);

        $rows = collect($ledger['lines'])->map(fn (array $line): array => array_map(
            fn (string $key) => $line[$key] ?? '',
            array_keys($columns),
        ));
        $rows->push(self::endingBalanceRow(array_keys($columns), $ledger['ending_balance']));

        return ExcelExport::download("general-ledger-{$account->code}.xlsx", array_values($columns), $rows);
    }

    /**
     * Resolves a comma-separated list of line keys to the ordered key =>
     * heading map. Unknown keys are ignored; an empty selection means all.
     *
     * @return array<string, string>
     */
    public static function columns(?string $requested): array
    {
        $keys = array_filter(explode(',', (string) $requested));

        return array_intersect_key(self::COLUMNS, array_flip($keys)) ?: self::COLUMNS;
    }

    /**
     * The total sits under the Balance column when it's shown; otherwise it
     * goes into the first cell as text so it isn't lost.
     *
     * @param  array<int, string>  $keys
     * @return array<int, mixed>
     */
    private static function endingBalanceRow(array $keys, float $endingBalance): array
    {
        $row = array_fill(0, count($keys), '');
        $balanceIndex = array_search('balance', $keys, true);

        if ($balanceIndex === false || $balanceIndex === 0) {
            $row[0] = 'Ending balance: '.$endingBalance;

            return $row;
        }

        $row[0] = 'Ending balance';
        $row[$balanceIndex] = $endingBalance;

        return $row;
    }
}
