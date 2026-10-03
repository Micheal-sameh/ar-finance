<?php

namespace App\Services;

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
     * @param  array{name: string, slug: string, base_currency: string, is_active?: bool}  $data
     */
    public function create(array $data): Tenant
    {
        $tenant = Tenant::create([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'base_currency' => $data['base_currency'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        (new ChartOfAccountsSeeder)->run($tenant->id);

        return $tenant;
    }

    /**
     * @param  array{name: string, slug: string, base_currency: string, is_active?: bool}  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'base_currency' => $data['base_currency'],
            'is_active' => $data['is_active'] ?? $tenant->is_active,
        ]);

        return $tenant;
    }
}
