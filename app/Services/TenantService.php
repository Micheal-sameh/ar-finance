<?php

namespace App\Services;

use App\Enums\TenantModule;
use App\Models\Tenant;
use App\Modules\Treasury\Services\TreasuryAccountProvisioner;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Pagination\LengthAwarePaginator;

class TenantService
{
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return Tenant::query()->orderBy('name')->paginate($perPage);
    }

    /**
     * The optional modules a tenant can be given, for the checkbox list.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public function moduleOptions(): array
    {
        return array_map(fn (TenantModule $module) => [
            'value' => $module->value,
            'label' => $module->label(),
            'description' => $module->description(),
        ], TenantModule::cases());
    }

    /**
     * @param  array{name: string, slug: string, base_currency: string, is_active?: bool, modules?: array<int, string>|null}  $data
     */
    public function create(array $data): Tenant
    {
        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'base_currency' => $data['base_currency'],
            'is_active' => $data['is_active'] ?? true,
            'is_treasury' => $data['is_treasury'] ?? false,
            'modules' => array_values(array_unique($data['modules'] ?? [])),
        ]);

        (new ChartOfAccountsSeeder)->run($tenant->id);
        $this->provisionTreasuryAccounts($tenant);

        return $tenant;
    }

    /**
     * @param  array{name: string, slug: string, base_currency: string, is_active?: bool, modules?: array<int, string>|null}  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'base_currency' => $data['base_currency'],
            'is_active' => $data['is_active'] ?? $tenant->is_active,
            'is_treasury' => $data['is_treasury'] ?? $tenant->is_treasury,
            'modules' => array_key_exists('modules', $data) ? array_values(array_unique($data['modules'] ?? [])) : $tenant->modules,
        ]);

        $this->provisionTreasuryAccounts($tenant);

        return $tenant;
    }

    /**
     * Switches a tenant's optional modules on or off — everything not in
     * the list is off.
     *
     * @param  array<int, string>  $modules
     */
    public function updateModules(Tenant $tenant, array $modules): Tenant
    {
        $tenant->update(['modules' => array_values(array_unique($modules))]);

        $this->provisionTreasuryAccounts($tenant);

        return $tenant;
    }

    /**
     * A tenant that uses the treasury (or is it) gets the ledger accounts and
     * mappings the treasury posts to; whatever already exists is left alone.
     */
    private function provisionTreasuryAccounts(Tenant $tenant): void
    {
        if ($tenant->hasModule(TenantModule::Treasury) || $tenant->is_treasury) {
            app(TreasuryAccountProvisioner::class)->provision($tenant);
        }
    }
}
