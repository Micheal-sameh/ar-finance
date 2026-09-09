<?php

namespace App\Repositories\Eloquent;

use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentAccountRepository implements AccountRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return Account::query()
            ->with('parent')
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['is_active'] ?? null, fn ($query, $active) => $query->where('is_active', $active))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return Account::query()
            ->with('parent')
            ->where('is_active', true)
            ->orderBy('code')
            ->get();
    }

    public function find(int $id): ?Account
    {
        return Account::query()->with(['parent', 'children'])->find($id);
    }

    public function findByCode(string $code): ?Account
    {
        return Account::query()->where('code', $code)->first();
    }

    public function create(array $attributes): Account
    {
        return Account::create($attributes);
    }

    public function update(Account $account, array $attributes): Account
    {
        $account->update($attributes);

        return $account;
    }

    public function delete(Account $account): bool
    {
        return $account->delete();
    }

    public function hasJournalLines(Account $account): bool
    {
        return $account->journalLines()->exists();
    }

    public function hasChildren(Account $account): bool
    {
        return $account->children()->exists();
    }
}
