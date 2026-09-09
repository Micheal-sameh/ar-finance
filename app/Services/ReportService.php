<?php

namespace App\Services;

use App\Models\Account;
use App\Repositories\Contracts\JournalRepositoryInterface;

/**
 * Every figure here is derived from posted journal_lines, never from
 * module tables (invoices/expenses/...) directly — the GL stays the
 * single source of truth for every report.
 */
class ReportService
{
    public function __construct(
        private readonly JournalRepositoryInterface $journals,
    ) {
    }

    /**
     * @return array{
     *     rows: array<int, array{account_id: int, code: string, name: string, type: string, debit: float, credit: float}>,
     *     total_debit: float,
     *     total_credit: float,
     *     is_balanced: bool,
     * }
     */
    public function trialBalance(?string $from = null, ?string $to = null): array
    {
        $lines = $this->journals->postedLinesWithAccounts($from, $to);

        $rows = $lines
            ->groupBy('account_id')
            ->map(function ($accountLines) {
                $account = $accountLines->first()->account;
                $totalDebit = (float) $accountLines->sum('debit');
                $totalCredit = (float) $accountLines->sum('credit');
                $net = round($totalDebit - $totalCredit, 2);

                return [
                    'account_id' => $account->id,
                    'code' => $account->code,
                    'name' => $account->name,
                    'type' => $account->type->value,
                    'debit' => $net > 0 ? $net : 0.0,
                    'credit' => $net < 0 ? abs($net) : 0.0,
                ];
            })
            ->filter(fn ($row) => $row['debit'] !== 0.0 || $row['credit'] !== 0.0)
            ->sortBy('code')
            ->values()
            ->all();

        $totalDebit = round(array_sum(array_column($rows, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($rows, 'credit')), 2);

        return [
            'rows' => $rows,
            'total_debit' => $totalDebit,
            'total_credit' => $totalCredit,
            'is_balanced' => abs($totalDebit - $totalCredit) < 0.005,
        ];
    }

    /**
     * Running balance for a single account, in date/entry order.
     *
     * @return array{
     *     lines: array<int, array{date: string, description: string, reference: ?string, debit: float, credit: float, balance: float}>,
     *     ending_balance: float,
     * }
     */
    public function generalLedger(Account $account, ?string $from = null, ?string $to = null): array
    {
        $lines = $this->journals->postedLinesForAccount($account->id, $from, $to)
            ->sortBy([
                fn ($line) => $line->journalEntry->date,
                fn ($line) => $line->journalEntry->id,
            ])
            ->values();

        $isDebitNormal = $account->normal_balance->value === 'debit';

        $balance = 0.0;
        $rows = $lines->map(function ($line) use (&$balance, $isDebitNormal) {
            $debit = (float) $line->debit;
            $credit = (float) $line->credit;
            $balance += $isDebitNormal ? ($debit - $credit) : ($credit - $debit);

            return [
                'date' => $line->journalEntry->date->toDateString(),
                'description' => $line->description ?? $line->journalEntry->description,
                'reference' => $line->journalEntry->reference,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => round($balance, 2),
            ];
        })->values()->all();

        return [
            'lines' => $rows,
            'ending_balance' => round($balance, 2),
        ];
    }
}
