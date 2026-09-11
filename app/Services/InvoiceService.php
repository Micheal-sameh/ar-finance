<?php

namespace App\Services;

use App\DTOs\CreateInvoiceData;
use App\DTOs\CreateJournalEntryData;
use App\DTOs\InvoiceLineData;
use App\DTOs\JournalLineData;
use App\Enums\JournalSourceType;
use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InvoiceService
{
    public function __construct(
        private readonly InvoiceRepositoryInterface $invoices,
        private readonly JournalService $journals,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->invoices->paginate($filters, $perPage);
    }

    public function find(int $id): ?Invoice
    {
        return $this->invoices->find($id);
    }

    /**
     * Drafts don't touch the ledger — only send() does. A draft is just a
     * quote-in-progress, not yet a real transaction.
     */
    public function create(CreateInvoiceData $data): Invoice
    {
        return $this->invoices->create(
            attributes: [
                'client_id' => $data->clientId,
                'invoice_number' => $data->invoiceNumber,
                'issue_date' => $data->issueDate,
                'due_date' => $data->dueDate,
                'currency' => $data->currency,
                'exchange_rate' => $data->exchangeRate,
                'receivable_account_id' => $data->receivableAccountId,
                'tax_payable_account_id' => $data->taxPayableAccountId,
                'status' => 'draft',
            ],
            lines: array_map(fn (InvoiceLineData $line) => [
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unitPrice,
                'tax_rate' => $line->taxRate,
                'account_id' => $line->accountId,
            ], $data->lines),
        );
    }

    /**
     * Recognizes revenue: debits the invoice's receivable control account
     * for the tax-inclusive total, credits each line's revenue account
     * for its pre-tax subtotal, and credits the tax portion separately to
     * tax_payable_account_id — a real VAT-payable split, not folded into
     * revenue.
     *
     * Everything posts in the tenant's base currency: each amount is
     * computed in the invoice's own currency first, then multiplied by
     * exchange_rate and rounded. The receivable debit is the *sum* of the
     * already-rounded credits (not total() * exchange_rate computed
     * independently) so per-line rounding can never throw the entry out
     * of balance.
     */
    public function send(Invoice $invoice): Invoice
    {
        if (! $invoice->status->isEditable()) {
            throw new RuntimeException("Invoice {$invoice->invoice_number} has already been sent.");
        }

        $totalTax = $invoice->totalTax();

        if ($totalTax > 0 && ! $invoice->tax_payable_account_id) {
            throw new RuntimeException("Invoice {$invoice->invoice_number} has tax on its lines but no tax payable account was set.");
        }

        return DB::transaction(function () use ($invoice) {
            $rate = (float) $invoice->exchange_rate;

            $creditLines = $invoice->lines->map(fn ($line) => new JournalLineData(
                accountId: $line->account_id,
                debit: 0,
                credit: round($line->subtotal() * $rate, 2),
                description: $line->description,
            ))->all();

            $taxBase = round($invoice->totalTax() * $rate, 2);
            if ($taxBase > 0) {
                $creditLines[] = new JournalLineData(
                    accountId: $invoice->tax_payable_account_id,
                    debit: 0,
                    credit: $taxBase,
                    description: "Tax on invoice {$invoice->invoice_number}",
                );
            }

            $receivableBase = round(
                array_sum(array_map(fn (JournalLineData $line) => $line->credit, $creditLines)),
                2,
            );

            $lines = [
                new JournalLineData(
                    accountId: $invoice->receivable_account_id,
                    debit: $receivableBase,
                    credit: 0,
                    description: "Invoice {$invoice->invoice_number}",
                ),
                ...$creditLines,
            ];

            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: $invoice->issue_date->toDateString(),
                description: "Invoice {$invoice->invoice_number} to {$invoice->client->name}",
                reference: $invoice->invoice_number,
                sourceType: JournalSourceType::Invoice,
                sourceId: $invoice->id,
                createdBy: auth()->id(),
                lines: $lines,
            ));

            return $this->invoices->updateStatus($invoice, 'sent');
        });
    }

    /**
     * Full payment only for now — partial payments and an AR-aging-aware
     * payments table land with the AR/AP Aging report phase.
     *
     * $settlementExchangeRate is the rate actually in effect when payment
     * lands — it can differ from the rate booked at send() time, since
     * rates move. When it does, the receivable clears at its *original*
     * booked base-currency value while cash is recorded at today's
     * base-currency value; the difference posts as a realized FX
     * gain/loss. Same-currency invoices (rate always 1) never hit this —
     * defaulting the settlement rate to the invoice's own rate means no
     * FX line is needed unless a caller explicitly passes a different one.
     */
    public function recordPayment(
        Invoice $invoice,
        int $paymentAccountId,
        ?float $settlementExchangeRate = null,
        ?int $fxGainLossAccountId = null,
    ): Invoice {
        if ($invoice->status->value !== 'sent') {
            throw new RuntimeException("Invoice {$invoice->invoice_number} is not awaiting payment.");
        }

        $settlementExchangeRate ??= (float) $invoice->exchange_rate;

        $bookedBase = round($invoice->total() * (float) $invoice->exchange_rate, 2);
        $settledBase = round($invoice->total() * $settlementExchangeRate, 2);
        $fxDiff = round($settledBase - $bookedBase, 2);

        if (abs($fxDiff) > 0.005 && ! $fxGainLossAccountId) {
            throw new RuntimeException("Payment settles at a different exchange rate than invoice {$invoice->invoice_number} was booked at — an FX gain/loss account is required.");
        }

        return DB::transaction(function () use ($invoice, $paymentAccountId, $bookedBase, $settledBase, $fxDiff, $fxGainLossAccountId) {
            $lines = [
                new JournalLineData(accountId: $paymentAccountId, debit: $settledBase, credit: 0),
                new JournalLineData(accountId: $invoice->receivable_account_id, debit: 0, credit: $bookedBase),
            ];

            if ($fxDiff > 0.005) {
                // Received more base currency than booked — realized gain.
                $lines[] = new JournalLineData(accountId: $fxGainLossAccountId, debit: 0, credit: $fxDiff);
            } elseif ($fxDiff < -0.005) {
                // Received less — realized loss.
                $lines[] = new JournalLineData(accountId: $fxGainLossAccountId, debit: abs($fxDiff), credit: 0);
            }

            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: now()->toDateString(),
                description: "Payment received for invoice {$invoice->invoice_number}",
                reference: $invoice->invoice_number,
                sourceType: JournalSourceType::Invoice,
                sourceId: $invoice->id,
                createdBy: auth()->id(),
                lines: $lines,
            ));

            return $this->invoices->updateStatus($invoice, 'paid', now());
        });
    }

    /**
     * Only a draft can be voided — nothing's posted yet, so there's
     * nothing to reverse. Voiding a sent/paid invoice needs a proper
     * reversing entry (credit notes), a separate later feature.
     */
    public function void(Invoice $invoice): Invoice
    {
        if (! $invoice->status->isEditable()) {
            throw new RuntimeException("Invoice {$invoice->invoice_number} has already been posted and can't be voided directly — issue a credit note instead.");
        }

        return $this->invoices->updateStatus($invoice, 'void');
    }
}
