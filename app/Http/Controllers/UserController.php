<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\Users\AssignUserRoleRequest;
use App\Http\Requests\Users\AssignUserTenantRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Services\UserService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserController extends Controller
{
    use ExportsExcel;

    public function __construct(
        private readonly UserService $users,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $viewingAllTenants = $this->tenantContext->isViewingAllTenants();

        $users = $this->users->paginate($request->only(['status', 'role', 'search']))
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'membership_code' => $user->avarewase_membership_code,
                'status' => $user->status->value,
                'roles' => $user->roles->pluck('name')->all(),
                'tenant_name' => $viewingAllTenants ? $user->tenants->pluck('name')->implode(', ') : null,
                'tenant_ids' => $viewingAllTenants ? $user->tenants->pluck('id')->all() : null,
            ]);

        $canAssignTenant = $viewingAllTenants && $request->user()->can('tenants.manage');
        $canAssignRole = $viewingAllTenants && $request->user()->can('users.manage');
        $canGrantPlatformAdmin = $request->user()->hasRole('Platform Admin');

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['status', 'role', 'search']),
            'canManage' => $viewingAllTenants ? false : $request->user()->can('manage', User::class),
            'canAssignTenant' => $canAssignTenant,
            'canAssignRole' => $canAssignRole,
            'canCreate' => $canAssignTenant,
            'availableRoles' => Role::query()->where('guard_name', 'web')
                ->when(! $canGrantPlatformAdmin, fn ($query) => $query->where('name', '!=', 'Platform Admin'))
                ->orderBy('name')
                ->pluck('name'),
            'tenants' => $canAssignTenant ? Tenant::query()->orderBy('name')->get(['id', 'name']) : [],
            'viewingAllTenants' => $viewingAllTenants,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('viewAny', User::class);

        $users = $this->users->paginate($request->only(['status', 'role', 'search']), $this->exportMaxRows());

        $rows = collect($users->items())->map(fn (User $user) => [
            $user->name,
            $user->email,
            $user->avarewase_membership_code,
            $user->roles->pluck('name')->implode(', '),
            $user->status->value,
        ]);

        return $this->exportXlsx('users.xlsx', ['Name', 'Email', 'Membership Code', 'Roles', 'Status'], $rows);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $role = $data['role'];
        $tenantIds = $data['tenant_ids'];
        unset($data['role'], $data['tenant_ids']);

        $this->users->create($data, $tenantIds, $role);

        return back()->with('success', 'User created.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($request->user(), $user);

        if ($user->is($request->user())) {
            return back()->with('error', "You can't edit your own account.");
        }

        $this->users->update($user, UserStatus::from($request->validated('status')), $request->validated('role'));

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function assignTenant(AssignUserTenantRequest $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($request->user(), $user);

        if ($user->is($request->user())) {
            return back()->with('error', "You can't reassign your own account.");
        }

        $this->users->assignTenants($user, array_map('intval', $request->validated('tenant_ids') ?? []));

        return redirect()->route('users.index')->with('success', 'User reassigned.');
    }

    public function assignRole(AssignUserRoleRequest $request, User $user): RedirectResponse
    {
        $this->ensureCanManage($request->user(), $user);

        if ($user->is($request->user())) {
            return back()->with('error', "You can't change your own role.");
        }

        $this->users->assignRole($user, $request->validated('role'));

        return redirect()->route('users.index')->with('success', 'User role updated.');
    }

    /**
     * User binding is unscoped, so an ordinary tenant admin must not reach
     * users of other tenants, and nobody but a Platform Admin may touch a
     * cross-tenant (Platform Admin / Portal Manager) account.
     */
    private function ensureCanManage(User $actor, User $target): void
    {
        if ($actor->isPlatformAdmin()) {
            abort_if($target->hasRole('Platform Admin') && ! $actor->hasRole('Platform Admin'), 403);

            return;
        }

        $tenantId = $this->tenantContext->id();

        abort_unless($tenantId !== null && $target->canAccessTenant($tenantId) && ! $target->isPlatformAdmin(), 403);
    }
}
