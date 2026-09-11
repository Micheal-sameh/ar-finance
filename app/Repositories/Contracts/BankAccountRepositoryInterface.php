<?php

namespace App\Repositories\Contracts;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BankAccountRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function all(): Collection;

    public function find(int $id): ?BankAccount;

    public function create(array $attributes): BankAccount;

    public function update(BankAccount $bankAccount, array $attributes): BankAccount;

    public function delete(BankAccount $bankAccount): bool;

    public function hasTransactions(BankAccount $bankAccount): bool;
}
