<?php

namespace App\Services;

use App\DTOs\CreateBankAccountData;
use App\Models\BankAccount;
use App\Repositories\Contracts\BankAccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

class BankAccountService
{
    public function __construct(
        private readonly BankAccountRepositoryInterface $bankAccounts,
    ) {
    }

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->bankAccounts->paginate($filters, $perPage);
    }

    public function all(): Collection
    {
        return $this->bankAccounts->all();
    }

    public function find(int $id): ?BankAccount
    {
        return $this->bankAccounts->find($id);
    }

    public function create(CreateBankAccountData $data): BankAccount
    {
        return $this->bankAccounts->create([
            'name' => $data->name,
            'account_id' => $data->accountId,
            'bank_name' => $data->bankName,
            'account_number' => $data->accountNumber,
            'currency' => $data->currency,
        ]);
    }

    public function update(BankAccount $bankAccount, CreateBankAccountData $data): BankAccount
    {
        return $this->bankAccounts->update($bankAccount, [
            'name' => $data->name,
            'account_id' => $data->accountId,
            'bank_name' => $data->bankName,
            'account_number' => $data->accountNumber,
            'currency' => $data->currency,
        ]);
    }

    public function delete(BankAccount $bankAccount): void
    {
        if ($this->bankAccounts->hasTransactions($bankAccount)) {
            throw new RuntimeException("Bank account \"{$bankAccount->name}\" cannot be deleted: it has imported transactions.");
        }

        $this->bankAccounts->delete($bankAccount);
    }
}
