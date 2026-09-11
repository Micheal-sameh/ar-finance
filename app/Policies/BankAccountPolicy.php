<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bank_accounts.view');
    }

    public function view(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('bank_accounts.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bank_accounts.manage');
    }

    public function update(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('bank_accounts.manage');
    }

    public function delete(User $user, BankAccount $bankAccount): bool
    {
        return $user->can('bank_accounts.manage');
    }
}
