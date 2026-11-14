<?php

namespace App\Services;

use App\Enums\TenantModule;
use App\Models\Tenant;
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
            'modules' => array_values(array_unique($data['modules'] ?? [])),
        ]);

        (new ChartOfAccountsSeeder)->run($tenant->id);

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
            'modules' => array_key_exists('modules', $data) ? array_values(array_unique($data['modules'] ?? [])) : $tenant->modules,
        ]);

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

        return $tenant;
    }
}
