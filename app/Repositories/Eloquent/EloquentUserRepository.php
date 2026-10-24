<?php

namespace App\Repositories\Eloquent;

use App\Models\AuditLog;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function paginate(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $tenantId = app(TenantContext::class)->id();

        return User::query()
            ->with(['roles', 'tenant:id,name'])
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->whereHas('roles', fn ($q) => $q->where('name', $role)))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?User
    {
        $tenantId = app(TenantContext::class)->id();

        return User::query()
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->find($id);
    }

    public function create(array $attributes, string $role): User
    {
        $user = User::query()->create($attributes);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([$role]);

        $this->audit($user, [], ['tenant_id' => $user->tenant_id, 'status' => $user->status->value, 'role' => [$role]], 'created');

        return $user->fresh(['roles', 'tenant']);
    }

    public function update(User $user, string $status, string $role): User
    {
        $before = ['status' => $user->status->value, 'role' => $user->getRoleNames()->all()];

        $user->update(['status' => $status]);
        $user->syncRoles([$role]);

        $this->audit($user, $before, ['status' => $status, 'role' => [$role]]);

        return $user->fresh('roles');
    }

    public function assignTenant(User $user, ?int $tenantId): User
    {
        $before = ['tenant_id' => $user->tenant_id];

        $user->update(['tenant_id' => $tenantId]);

        $this->audit($user, $before, ['tenant_id' => $tenantId]);

        return $user->fresh('tenant');
    }

    public function assignRole(User $user, string $role): User
    {
        $before = ['role' => $user->getRoleNames()->all()];

        $user->syncRoles([$role]);

        $this->audit($user, $before, ['role' => [$role]]);

        return $user->fresh('roles');
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function audit(User $user, array $before, array $after, string $action = 'updated'): void
    {
        AuditLog::create([
            'tenant_id' => $user->tenant_id,
            'user_id' => auth()->id(),
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'action' => $action,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
