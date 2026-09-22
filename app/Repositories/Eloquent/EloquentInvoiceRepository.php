<?php

namespace App\Repositories\Eloquent;

use App\Models\Invoice;
use App\Repositories\Contracts\InvoiceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentInvoiceRepository implements InvoiceRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Invoice::query()
            ->with(['client', 'lines'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('issue_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('issue_date', '<=', $to))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?Invoice
    {
        return Invoice::query()->with(['client', 'lines.account', 'receivableAccount'])->find($id);
    }

    public function create(array $attributes, array $lines): Invoice
    {
        $invoice = Invoice::create($attributes);
        $invoice->lines()->createMany($lines);

        return $invoice->load(['lines.account', 'client']);
    }

    public function updateStatus(Invoice $invoice, string $status, ?\DateTimeInterface $paidAt = null): Invoice
    {
        $invoice->update(['status' => $status, 'paid_at' => $paidAt]);

        return $invoice;
    }

    public function existsByNumber(int $tenantId, string $invoiceNumber): bool
    {
        return Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('invoice_number', $invoiceNumber)
            ->exists();
    }

    public function postedBetween(string $from, string $to): Collection
    {
        return Invoice::query()
            ->with('lines')
            ->whereIn('status', ['sent', 'paid'])
            ->whereDate('issue_date', '>=', $from)
            ->whereDate('issue_date', '<=', $to)
            ->get();
    }

    public function outstanding(): Collection
    {
        return Invoice::query()
            ->with(['client', 'lines', 'receivableAccount'])
            ->whereIn('status', ['sent', 'overdue'])
            ->orderBy('issue_date')
            ->get();
    }

    public function updateRevaluedRate(Invoice $invoice, float $rate): Invoice
    {
        $invoice->update(['revalued_exchange_rate' => $rate]);

        return $invoice;
    }
}
