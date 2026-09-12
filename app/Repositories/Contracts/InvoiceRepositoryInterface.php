<?php

namespace App\Repositories\Contracts;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface InvoiceRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?Invoice;

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function create(array $attributes, array $lines): Invoice;

    public function updateStatus(Invoice $invoice, string $status, ?\DateTimeInterface $paidAt = null): Invoice;

    public function existsByNumber(int $tenantId, string $invoiceNumber): bool;

    /**
     * Sent/paid invoices issued in a date range, lines eager-loaded — for
     * the VAT return report (output VAT).
     */
    public function postedBetween(string $from, string $to): Collection;

    /**
     * Sent/overdue (i.e. posted but not yet paid or void) invoices, lines
     * eager-loaded — the candidate set for currency revaluation, and
     * usable later for AR aging.
     */
    public function outstanding(): Collection;

    public function updateRevaluedRate(Invoice $invoice, float $rate): Invoice;
}
