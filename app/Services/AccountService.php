<?php

namespace App\Services;

use App\DTOs\CreateAccountData;
use App\Exceptions\AccountInUseException;
use App\Models\Account;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class AccountService
{
    public function __construct(
        private readonly AccountRepositoryInterface $accounts,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->accounts->paginate($filters, $perPage);
    }

    public function all(): Collection
    {
        return $this->accounts->all();
    }

    public function find(int $id): ?Account
    {
        return $this->accounts->find($id);
    }

    public function create(CreateAccountData $data): Account
    {
        return $this->accounts->create([
            'code' => $data->code,
            'name' => $data->name,
            'type' => $data->type,
            'normal_balance' => $data->normalBalance,
            'parent_id' => $data->parentId,
            'is_active' => $data->isActive,
        ]);
    }

    public function update(Account $account, CreateAccountData $data): Account
    {
        return $this->accounts->update($account, [
            'code' => $data->code,
            'name' => $data->name,
            'type' => $data->type,
            'normal_balance' => $data->normalBalance,
            'parent_id' => $data->parentId,
            'is_active' => $data->isActive,
        ]);
    }

    /**
     * @throws AccountInUseException
     */
    public function delete(Account $account): void
    {
        if ($this->accounts->hasJournalLines($account)) {
            throw AccountInUseException::hasJournalLines($account->code);
        }

        if ($this->accounts->hasChildren($account)) {
            throw AccountInUseException::hasChildren($account->code);
        }

        $this->accounts->delete($account);
    }
}
