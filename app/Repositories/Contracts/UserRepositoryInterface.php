<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?User;

    /**
     * @param  array{name: string, email: ?string, phone: ?string, password: string, tenant_id: ?int, status: string}  $attributes
     */
    public function create(array $attributes, string $role): User;

    public function update(User $user, string $status, string $role): User;

    public function assignTenant(User $user, ?int $tenantId): User;

    public function assignRole(User $user, string $role): User;
}
