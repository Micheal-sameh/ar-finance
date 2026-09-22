<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Controllers\Concerns\ExportsExcel;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
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
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = $this->users->paginate($request->only(['status', 'role', 'search']))
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'membership_code' => $user->avarewase_membership_code,
                'status' => $user->status->value,
                'roles' => $user->roles->pluck('name')->all(),
            ]);

        return Inertia::render('Settings/Users/Index', [
            'users' => $users,
            'filters' => $request->only(['status', 'role', 'search']),
            'canManage' => $request->user()->can('manage', User::class),
            'availableRoles' => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name'),
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

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', "You can't edit your own account.");
        }

        $this->users->update($user, UserStatus::from($request->validated('status')), $request->validated('role'));

        return redirect()->route('users.index')->with('success', 'User updated.');
    }
}
