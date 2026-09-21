<?php

namespace App\Listeners;

use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use Avarewase\SsoClient\Events\AvarewaseUserAuthenticated;

/**
 * A user provisioned for the first time by an Avarewase SSO login (see
 * DefaultAvarewaseUserProvisioner) starts with no local role and no
 * access — an admin has to actively grant them one from the Users page
 * before they can do anything. `wasRecentlyCreated` is what tells us
 * this login just created the row, rather than logging an existing user
 * back in, since the provisioner returns the same model instance either
 * way.
 *
 * The provisioner also never sets `tenant_id` — without it, the user
 * can authenticate but every tenant-scoped write (accounts, invoices,
 * etc.) fails on a NOT NULL constraint. Until this app supports more
 * than one tenant, every new SSO login is placed on the first tenant.
 */
class AssignDefaultsToNewAvarewaseUsers
{
    public function handle(AvarewaseUserAuthenticated $event): void
    {
        $user = $event->user;

        if (! $user instanceof User || ! $user->wasRecentlyCreated) {
            return;
        }

        $user->syncRoles(['Viewer']);
        $user->update([
            'status' => UserStatus::Suspended,
            'tenant_id' => Tenant::query()->orderBy('id')->value('id'),
        ]);
    }
}
