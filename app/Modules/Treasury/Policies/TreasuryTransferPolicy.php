<?php

namespace App\Modules\Treasury\Policies;

use App\Enums\TenantModule;
use App\Models\User;
use App\Support\TenantContext;

/**
 * The treasury tenant's treasurer manages (accepts, funds, moves); any other
 * tenant's admins can only ask. The tenant being acted in decides which of
 * the two this is, not the user's role, so a Super Admin of an ordinary
 * tenant can never accept their own request.
 */
class TreasuryTransferPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('treasury.view');
    }

    public function request(User $user): bool
    {
        $tenant = app(TenantContext::class)->tenant();

        return $user->can('treasury.request')
            && $tenant !== null
            && ! $tenant->is_treasury
            && $tenant->hasModule(TenantModule::Treasury);
    }

    public function manage(User $user): bool
    {
        return $user->can('treasury.manage') && (bool) app(TenantContext::class)->tenant()?->is_treasury;
    }
}
