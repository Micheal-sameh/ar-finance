<?php

namespace App\Policies;

use App\Models\User;

/**
 * Budgets are part of the books, so they follow the chart-of-accounts permissions.
 */
class BudgetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('accounts.view');
    }

    public function manage(User $user): bool
    {
        return $user->can('accounts.manage');
    }
}
