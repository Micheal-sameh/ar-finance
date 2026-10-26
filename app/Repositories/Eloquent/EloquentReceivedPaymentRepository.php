<?php

namespace App\Repositories\Eloquent;

use App\Models\ReceivedPayment;
use App\Repositories\Contracts\ReceivedPaymentRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentReceivedPaymentRepository implements ReceivedPaymentRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return ReceivedPayment::query()
            ->with(['client:id,name', 'depositAccount:id,name', 'creditAccount:id,name', 'tenant:id,name'])
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('received_on', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('received_on', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($inner) => $inner->where('description', 'like', "%{$search}%")->orWhere('reference', 'like', "%{$search}%"),
            ))
            ->orderByDesc('received_on')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?ReceivedPayment
    {
        return ReceivedPayment::query()->with(['client', 'depositAccount', 'creditAccount'])->find($id);
    }

    public function create(array $attributes): ReceivedPayment
    {
        return ReceivedPayment::create($attributes);
    }
}
