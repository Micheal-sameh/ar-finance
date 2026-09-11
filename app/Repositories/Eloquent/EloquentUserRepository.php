<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        return User::query()
            ->with('roles')
            ->where('tenant_id', auth()->user()?->tenant_id)
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?User
    {
        return User::where('tenant_id', auth()->user()?->tenant_id)->find($id);
    }

    public function update(User $user, string $status, string $role): User
    {
        $user->update(['status' => $status]);
        $user->syncRoles([$role]);

        return $user->fresh('roles');
    }
}
