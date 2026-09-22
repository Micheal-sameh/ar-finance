<?php

namespace App\Repositories\Eloquent;

use App\Models\Bill;
use App\Repositories\Contracts\BillRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentBillRepository implements BillRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Bill::query()
            ->with(['vendor', 'lines'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('bill_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('bill_date', '<=', $to))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('bill_number', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('bill_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?Bill
    {
        return Bill::query()->with(['vendor', 'lines.account', 'payableAccount', 'purchaseOrder'])->find($id);
    }

    public function create(array $attributes, array $lines): Bill
    {
        $bill = Bill::create($attributes);
        $bill->lines()->createMany($lines);

        return $bill->load(['lines.account', 'vendor']);
    }

    public function updateStatus(Bill $bill, string $status, ?\DateTimeInterface $paidAt = null): Bill
    {
        $bill->update(['status' => $status, 'paid_at' => $paidAt]);

        return $bill;
    }

    public function postedBetween(string $from, string $to): Collection
    {
        return Bill::query()
            ->with('lines')
            ->whereIn('status', ['approved', 'paid'])
            ->whereDate('bill_date', '>=', $from)
            ->whereDate('bill_date', '<=', $to)
            ->get();
    }

    public function outstanding(): Collection
    {
        return Bill::query()
            ->with(['vendor', 'lines'])
            ->where('status', 'approved')
            ->orderBy('due_date')
            ->get();
    }
}
