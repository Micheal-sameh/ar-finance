<?php

namespace App\Repositories\Contracts;

use App\Models\Account;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface AccountRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    /**
     * All active accounts eager-loaded with their parent, for pickers/trees.
     */
    public function all(): Collection;

    /**
     * Every account (active or not) matching the given filters, unpaginated
     * and ordered by code — used to render the Chart of Accounts as a tree.
     */
    public function filtered(array $filters = []): Collection;

    public function find(int $id): ?Account;

    public function findByCode(string $code): ?Account;

    public function create(array $attributes): Account;

    public function update(Account $account, array $attributes): Account;

    public function delete(Account $account): bool;

    public function hasJournalLines(Account $account): bool;

    public function hasChildren(Account $account): bool;
}
