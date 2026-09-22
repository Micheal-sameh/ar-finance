<?php

namespace App\Repositories\Eloquent;

use App\Models\BankAccount;
use App\Repositories\Contracts\BankAccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentBankAccountRepository implements BankAccountRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return BankAccount::query()
            ->with('account')
            ->when($filters['currency'] ?? null, fn ($query, $currency) => $query->where('currency', $currency))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(): Collection
    {
        return BankAccount::query()->with('account')->orderBy('name')->get();
    }

    public function find(int $id): ?BankAccount
    {
        return BankAccount::query()->with('account')->find($id);
    }

    public function create(array $attributes): BankAccount
    {
        return BankAccount::create($attributes);
    }

    public function update(BankAccount $bankAccount, array $attributes): BankAccount
    {
        $bankAccount->update($attributes);

        return $bankAccount;
    }

    public function delete(BankAccount $bankAccount): bool
    {
        return $bankAccount->delete();
    }

    public function hasTransactions(BankAccount $bankAccount): bool
    {
        return $bankAccount->bankTransactions()->exists();
    }
}
