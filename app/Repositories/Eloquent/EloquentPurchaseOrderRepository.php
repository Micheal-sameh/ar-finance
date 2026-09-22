<?php

namespace App\Repositories\Eloquent;

use App\Models\PurchaseOrder;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentPurchaseOrderRepository implements PurchaseOrderRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return PurchaseOrder::query()
            ->with(['vendor', 'lines'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('order_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('order_date', '<=', $to))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('po_number', 'like', "%{$search}%")
                        ->orWhereHas('vendor', fn ($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?PurchaseOrder
    {
        return PurchaseOrder::query()->with(['vendor', 'lines.account', 'bills'])->find($id);
    }

    public function create(array $attributes, array $lines): PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::create($attributes);
        $purchaseOrder->lines()->createMany($lines);

        return $purchaseOrder->load(['lines.account', 'vendor']);
    }

    public function updateStatus(PurchaseOrder $purchaseOrder, string $status): PurchaseOrder
    {
        $purchaseOrder->update(['status' => $status]);

        return $purchaseOrder;
    }
}
