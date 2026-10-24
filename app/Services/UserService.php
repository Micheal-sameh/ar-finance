<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class UserService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {}

    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return $this->users->paginate($filters, $perPage);
    }

    /**
     * @param  array{name: string, email: ?string, phone: ?string, password: string, status: string}  $attributes
     * @param  array<int, int>  $tenantIds
     */
    public function create(array $attributes, array $tenantIds, string $role): User
    {
        return $this->users->create($attributes, $tenantIds, $role);
    }

    public function update(User $user, UserStatus $status, string $role): User
    {
        return $this->users->update($user, $status->value, $role);
    }

    /**
     * @param  array<int, int>  $tenantIds
     */
    public function assignTenants(User $user, array $tenantIds): User
    {
        return $this->users->assignTenants($user, $tenantIds);
    }

    public function assignRole(User $user, string $role): User
    {
        return $this->users->assignRole($user, $role);
    }
}
