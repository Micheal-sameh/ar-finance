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
                'status' => 'draft',
            ],
            lines: array_map(fn (\App\DTOs\InvoiceLineData $line) => [
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
     * for the tax-inclusive total, credits each line's revenue account.
     *
     * Tax is folded into the revenue-account credit rather than split out
     * to a VAT-payable control account — there's no tax-account concept
     * yet (that lands with the Tax/VAT phase). The entry still balances;
     * revisit once that phase adds a proper tax control account.
     */
    public function send(Invoice $invoice): Invoice
    {
        if (! $invoice->status->isEditable()) {
            throw new RuntimeException("Invoice {$invoice->invoice_number} has already been sent.");
        }

        return DB::transaction(function () use ($invoice) {
            $lines = [
                new JournalLineData(
                    accountId: $invoice->receivable_account_id,
                    debit: $invoice->total(),
                    credit: 0,
                    description: "Invoice {$invoice->invoice_number}",
                ),
                ...$invoice->lines->map(fn ($line) => new JournalLineData(
                    accountId: $line->account_id,
                    debit: 0,
                    credit: $line->lineTotal(),
                    description: $line->description,
                ))->all(),
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
     */
    public function recordPayment(Invoice $invoice, int $paymentAccountId): Invoice
    {
        if ($invoice->status->value !== 'sent') {
            throw new RuntimeException("Invoice {$invoice->invoice_number} is not awaiting payment.");
        }

        return DB::transaction(function () use ($invoice, $paymentAccountId) {
            $this->journals->postJournalEntry(new CreateJournalEntryData(
                date: now()->toDateString(),
                description: "Payment received for invoice {$invoice->invoice_number}",
                reference: $invoice->invoice_number,
                sourceType: JournalSourceType::Invoice,
                sourceId: $invoice->id,
                createdBy: auth()->id(),
                lines: [
                    new JournalLineData(accountId: $paymentAccountId, debit: $invoice->total(), credit: 0),
                    new JournalLineData(accountId: $invoice->receivable_account_id, debit: 0, credit: $invoice->total()),
                ],
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
