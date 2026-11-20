<?php

namespace App\Modules\Treasury\Services;

use App\Enums\AccountType;
use App\Enums\GlMappingRole;
use App\Models\Account;
use App\Models\GlMapping;
use App\Models\Tenant;

/**
 * Makes sure a tenant has the ledger accounts the treasury posts to, and
 * that its GL mapping points at them. Safe to run again and again: a role
 * that is already mapped is left alone, an account that already exists (by
 * name) is mapped instead of duplicated, and only what is missing is created.
 *
 * Works on the tenant it is given, whoever is logged in — it never relies on
 * the current tenant context.
 */
class TreasuryAccountProvisioner
{
    /**
     * What each role needs: the account to create when nothing suitable exists.
     *
     * @var array<string, array{name: string, type: AccountType, parent: string}>
     */
    private const DEFINITIONS = [
        'treasury_cash' => ['name' => 'Cash on Hand', 'type' => AccountType::Asset, 'parent' => '1200'],
        'treasury_receivable' => ['name' => 'Due from Treasury', 'type' => AccountType::Asset, 'parent' => '1200'],
        'treasury_payable' => ['name' => 'Due to Treasury', 'type' => AccountType::Liability, 'parent' => '2200'],
        'treasury_held' => ['name' => 'Held for Tenants', 'type' => AccountType::Liability, 'parent' => '2200'],
        'treasury_advances' => ['name' => 'Advances to Tenants', 'type' => AccountType::Asset, 'parent' => '1200'],
        'treasury_safe' => ['name' => 'Treasury Safe', 'type' => AccountType::Asset, 'parent' => '1200'],
    ];

    /**
     * Cash accounts a tenant already maps for other modules — reused as its
     * treasury cash account before a new one is made.
     *
     * @var array<int, GlMappingRole>
     */
    private const EXISTING_CASH_ROLES = [
        GlMappingRole::DonationCashPayment,
        GlMappingRole::BookingCashPayment,
    ];

    /**
     * @return array<int, array{role: string, status: 'mapped'|'linked'|'created', account: string}>
     */
    public function provision(Tenant $tenant): array
    {
        $roles = $tenant->is_treasury
            ? [GlMappingRole::TreasuryHeld, GlMappingRole::TreasuryAdvances]
            : [GlMappingRole::TreasuryCash, GlMappingRole::TreasuryReceivable, GlMappingRole::TreasuryPayable];

        $results = array_map(fn (GlMappingRole $role) => $this->ensureRole($tenant, $role), $roles);

        if ($tenant->is_treasury) {
            $this->ensureAccount($tenant, self::DEFINITIONS['treasury_safe']);
        }

        return $results;
    }

    /**
     * @return array{role: string, status: 'mapped'|'linked'|'created', account: string}
     */
    private function ensureRole(Tenant $tenant, GlMappingRole $role): array
    {
        $mapping = GlMapping::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', $role->value)->first();

        if ($mapping !== null) {
            $account = Account::query()->withoutGlobalScopes()->find($mapping->account_id);

            return ['role' => $role->value, 'status' => 'mapped', 'account' => $account ? "{$account->code} {$account->name}" : '—'];
        }

        $definition = self::DEFINITIONS[$role->value];
        $existing = $role === GlMappingRole::TreasuryCash ? $this->existingCashAccount($tenant) : null;
        $existing ??= $this->accountNamed($tenant, $definition['name'], $definition['type']);
        $created = false;

        if ($existing === null) {
            $existing = $this->ensureAccount($tenant, $definition, $created);
        }

        GlMapping::query()->withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'role' => $role->value, 'account_id' => $existing->id]);

        return ['role' => $role->value, 'status' => $created ? 'created' : 'linked', 'account' => "{$existing->code} {$existing->name}"];
    }

    private function existingCashAccount(Tenant $tenant): ?Account
    {
        foreach (self::EXISTING_CASH_ROLES as $role) {
            $accountId = GlMapping::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('role', $role->value)->value('account_id');
            $account = $accountId ? Account::query()->withoutGlobalScopes()->find($accountId) : null;

            if ($account !== null && $account->type === AccountType::Asset && $account->is_active) {
                return $account;
            }
        }

        return null;
    }

    private function accountNamed(Tenant $tenant, string $name, AccountType $type): ?Account
    {
        return Account::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('type', $type->value)
            ->where('is_active', true)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->whereDoesntHave('children')
            ->first();
    }

    /**
     * Finds the account by name or creates it under its usual parent.
     *
     * @param  array{name: string, type: AccountType, parent: string}  $definition
     */
    private function ensureAccount(Tenant $tenant, array $definition, bool &$created = false): Account
    {
        $existing = $this->accountNamed($tenant, $definition['name'], $definition['type']);

        if ($existing !== null) {
            return $existing;
        }

        $created = true;
        $parent = $this->usableParent($tenant, $definition['parent'], $definition['type']);

        return Account::query()->withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'code' => $this->freeCode($tenant, $definition['parent'], $parent !== null),
            'name' => $definition['name'],
            'type' => $definition['type'],
            'normal_balance' => $definition['type']->defaultNormalBalance(),
            'currency' => strtoupper($tenant->base_currency ?: 'EGP'),
            'parent_id' => $parent?->id,
            'is_active' => true,
            'is_deletable' => false,
        ]);
    }

    /**
     * The parent to nest under — only when it is a real grouping account of
     * the right type. An account that already carries postings is never made
     * a parent, because a parent can no longer be posted to.
     */
    private function usableParent(Tenant $tenant, string $code, AccountType $type): ?Account
    {
        $parent = Account::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('code', $code)->first();

        if ($parent === null || $parent->type !== $type || $parent->journalLines()->exists()) {
            return null;
        }

        return $parent;
    }

    /**
     * Next unused code. Nested accounts follow the chart convention (a child
     * of 1200 is 1210, 1220, …); un-nested ones count up from the parent's
     * thousand (1900, 1910, …) so they stay in the right section.
     */
    private function freeCode(Tenant $tenant, string $parentCode, bool $nested): string
    {
        $taken = Account::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->pluck('code')->all();
        $base = (int) $parentCode;
        $candidates = $nested
            ? range($base + 10, $base + 90, 10)
            : range(((int) floor($base / 1000) * 1000) + 900, ((int) floor($base / 1000) * 1000) + 990, 10);

        foreach ($candidates as $candidate) {
            if (! in_array((string) $candidate, $taken, true)) {
                return (string) $candidate;
            }
        }

        return (string) ($base + random_int(91, 99));
    }
}
