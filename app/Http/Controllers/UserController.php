<?php

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $users = $this->users->paginate($request->only(['search']))
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
            'filters' => $request->only(['search']),
            'canManage' => $request->user()->can('manage', User::class),
            'availableRoles' => Role::query()->where('guard_name', 'web')->orderBy('name')->pluck('name'),
        ]);
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
