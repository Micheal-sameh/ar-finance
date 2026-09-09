<?php

namespace App\Repositories\Contracts;

use App\Models\Invoice;
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
}
