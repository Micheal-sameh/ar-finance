<?php

namespace App\Services;

use App\DTOs\CreateJournalEntryData;
use App\DTOs\JournalLineData;
use App\Enums\JournalSourceType;
use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * Period-end re-measurement of foreign-currency receivables against
 * today's exchange rate — the "revalue the accounts that hold another
 * currency" feature. Scoped to what the app actually tracks foreign
 * currency for today: invoices' receivable accounts. Bank accounts are
 * deliberately out of scope (reconciliation, not revaluation, is how
 * those get kept correct) and there's no other place in the app right now
 * where a GL account carries a non-base-currency balance.
 *
 * Unlike InvoiceService::recordPayment()'s *realized* gain/loss (cash
 * actually changed hands at a different rate), this posts an *unrealized*
 * gain/loss: the invoice is still outstanding, only its reporting-currency
 * measurement moved. Each run only posts the incremental difference since
 * the last revaluation (or since send() if never revalued) — see
 * Invoice::bookedExchangeRate().
 */
class CurrencyRevaluationService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly ExchangeRateService $exchangeRates,
        private readonly JournalService $journals,
    ) {
    }

    /**
     * @return array{
     *     base_currency: string,
     *     as_of: string,
     *     rows: array<int, array{invoice_id: int, invoice_number: string, client_name: string, currency: string, foreign_amount: float, old_rate: float, new_rate: float, old_base_value: float, new_base_value: float, unrealized_gain_loss: float}>,
     *     total_unrealized_gain_loss: float,
     * }
     */
    public function preview(string $asOfDate): array
    {
        $base = $this->exchangeRates->baseCurrency();
        $rows = [];

        foreach ($this->candidates($base) as $invoice) {
            $row = $this->computeRow($invoice, $base, $asOfDate);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return [
            'base_currency' => $base,
            'as_of' => $asOfDate,
            'rows' => $rows,
            'total_unrealized_gain_loss' => round(array_sum(array_column($rows, 'unrealized_gain_loss')), 2),
        ];
    }

    /**
     * Posts one adjusting journal entry per invoice whose measurement
     * actually moved, and advances its booked rate so the next run only
     * ever posts the incremental change. Invoices for a currency whose
     * rate can't be found are skipped (not fatal) and left for a later run.
     *
     * @return array{posted: int, skipped: int, total_unrealized_gain_loss: float}
     */
    public function revalueAll(string $asOfDate, int $fxGainLossAccountId): array
    {
        $base = $this->exchangeRates->baseCurrency();
        $posted = 0;
        $skipped = 0;
        $total = 0.0;

        foreach ($this->candidates($base) as $invoice) {
            $row = $this->computeRow($invoice, $base, $asOfDate);

            if ($row === null) {
                $skipped++;

                continue;
            }

            DB::transaction(function () use ($invoice, $row, $fxGainLossAccountId, $asOfDate) {
                $this->postRevaluation($invoice, $row, $fxGainLossAccountId, $asOfDate);
                $this->invoices->updateRevaluedRate($invoice, $row['new_rate']);
            });

            $posted++;
            $total += $row['unrealized_gain_loss'];
        }

        return ['posted' => $posted, 'skipped' => $skipped, 'total_unrealized_gain_loss' => round($total, 2)];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Invoice>
     */
    private function candidates(string $base): \Illuminate\Support\Collection
    {
        return $this->invoices->outstanding()
            ->filter(fn (Invoice $invoice) => strtoupper($invoice->currency) !== $base);
    }

    /**
     * @return array{invoice_id: int, invoice_number: string, client_name: string, currency: string, foreign_amount: float, old_rate: float, new_rate: float, old_base_value: float, new_base_value: float, unrealized_gain_loss: float}|null
     */
    private function computeRow(Invoice $invoice, string $base, string $asOfDate): ?array
    {
        $newRate = $this->exchangeRates->rateFor($invoice->currency, $asOfDate);

        if ($newRate === null) {
            return null;
        }

        $foreignAmount = $invoice->total();
        $oldRate = $invoice->bookedExchangeRate();
        $oldBase = round($foreignAmount * $oldRate, 2);
        $newBase = round($foreignAmount * $newRate, 2);
        $diff = round($newBase - $oldBase, 2);

        if (abs($diff) < 0.005) {
            return null;
        }

        return [
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'client_name' => $invoice->client->name,
            'currency' => $invoice->currency,
            'foreign_amount' => $foreignAmount,
            'old_rate' => $oldRate,
            'new_rate' => $newRate,
            'old_base_value' => $oldBase,
            'new_base_value' => $newBase,
            'unrealized_gain_loss' => $diff,
        ];
    }

    private function postRevaluation(Invoice $invoice, array $row, int $fxGainLossAccountId, string $asOfDate): void
    {
        $diff = $row['unrealized_gain_loss'];

        // Receivable moved up (gain) → debit it further; moved down (loss)
        // → credit it back down. The FX line is always the opposite side.
        $lines = $diff > 0
            ? [
                new JournalLineData(accountId: $invoice->receivable_account_id, debit: $diff, credit: 0),
                new JournalLineData(accountId: $fxGainLossAccountId, debit: 0, credit: $diff),
            ]
            : [
                new JournalLineData(accountId: $fxGainLossAccountId, debit: abs($diff), credit: 0),
                new JournalLineData(accountId: $invoice->receivable_account_id, debit: 0, credit: abs($diff)),
            ];

        $this->journals->postJournalEntry(new CreateJournalEntryData(
            date: $asOfDate,
            description: "Unrealized FX revaluation — invoice {$invoice->invoice_number} ({$row['currency']} @ {$row['new_rate']})",
            reference: $invoice->invoice_number,
            sourceType: JournalSourceType::Revaluation,
            sourceId: $invoice->id,
            createdBy: auth()->id(),
            lines: $lines,
        ));
    }
}
