<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface UserRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator;

    public function find(int $id): ?User;

    /**
     * @param  array{name: string, email: ?string, phone: ?string, password: string, status: string}  $attributes
     * @param  array<int, int>  $tenantIds  the first one becomes the user's default tenant
     */
    public function create(array $attributes, array $tenantIds, string $role): User;

    public function update(User $user, string $status, string $role): User;

    public function updateDetails(User $user, string $name, ?string $email, ?string $phone): User;

    /**
     * @param  array<int, int>  $tenantIds
     */
    public function assignTenants(User $user, array $tenantIds): User;

    public function assignRole(User $user, string $role): User;
}
