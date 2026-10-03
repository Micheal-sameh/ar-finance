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

    public function update(User $user, UserStatus $status, string $role): User
    {
        return $this->users->update($user, $status->value, $role);
    }

    public function assignTenant(User $user, ?int $tenantId): User
    {
        return $this->users->assignTenant($user, $tenantId);
    }

    public function assignRole(User $user, string $role): User
    {
        return $this->users->assignRole($user, $role);
    }
}
