<?php

namespace App\Repositories\Eloquent;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentClientRepository implements ClientRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Client::query()
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Client::query()->orderBy('name')->get();
    }

    public function find(int $id): ?Client
    {
        return Client::find($id);
    }

    public function create(array $attributes): Client
    {
        return Client::create($attributes);
    }

    public function update(Client $client, array $attributes): Client
    {
        $client->update($attributes);

        return $client;
    }

    public function delete(Client $client): bool
    {
        return $client->delete();
    }

    public function hasInvoices(Client $client): bool
    {
        return $client->invoices()->exists();
    }

    public function distinctCurrencies(): array
    {
        return Client::query()
            ->whereNotNull('currency')
            ->distinct()
            ->orderBy('currency')
            ->pluck('currency')
            ->all();
    }
}
