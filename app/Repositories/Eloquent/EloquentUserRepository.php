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
            ->with(['roles', 'tenants:id,name'])
            ->when($tenantId !== null, fn ($query) => $query->whereHas('tenants', fn ($q) => $q->whereKey($tenantId)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['role'] ?? null, fn ($query, $role) => $query->whereHas('roles', fn ($q) => $q->where('name', $role)))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($query) => $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%"),
            ))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function find(int $id): ?User
    {
        $tenantId = app(TenantContext::class)->id();

        return User::query()
            ->when($tenantId !== null, fn ($query) => $query->whereHas('tenants', fn ($q) => $q->whereKey($tenantId)))
            ->find($id);
    }

    public function create(array $attributes, array $tenantIds, string $role): User
    {
        $user = User::query()->create($attributes + ['tenant_id' => $tenantIds[0] ?? null]);
        $user->tenants()->syncWithoutDetaching($tenantIds);
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->syncRoles([$role]);

        $this->audit($user, [], ['tenant_ids' => $tenantIds, 'status' => $user->status->value, 'role' => [$role]], 'created');

        return $user->fresh(['roles', 'tenants']);
    }

    public function update(User $user, string $status, string $role): User
    {
        $before = ['status' => $user->status->value, 'role' => $user->getRoleNames()->all()];

        $user->update(['status' => $status]);
        $user->syncRoles([$role]);

        $this->audit($user, $before, ['status' => $status, 'role' => [$role]]);

        return $user->fresh('roles');
    }

    public function updateDetails(User $user, string $name, ?string $email, ?string $phone): User
    {
        $before = ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone];

        $user->update(['name' => $name, 'email' => $email, 'phone' => $phone]);

        $this->audit($user, $before, ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone]);

        return $user->fresh();
    }

    public function assignTenants(User $user, array $tenantIds): User
    {
        $before = ['tenant_ids' => $user->tenants()->pluck('tenants.id')->all()];

        $user->tenants()->sync($tenantIds);

        // The default tenant must stay one of the user's tenants.
        $defaultTenantId = in_array($user->tenant_id, $tenantIds, true) ? $user->tenant_id : ($tenantIds[0] ?? null);
        $user->update(['tenant_id' => $defaultTenantId]);

        $this->audit($user, $before, ['tenant_ids' => $tenantIds]);

        return $user->fresh(['tenant', 'tenants']);
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
