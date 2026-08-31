<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Enums\Role as RoleEnum;
use App\Http\Requests\Users\DeleteUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Queries\Users\UserListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role as RoleModel;

final readonly class UserController
{
    public function index(Request $request, UserListingQuery $query): Response
    {
        $filters = [
            'search' => $request->string('search')->value(),
            'status' => $request->string('status', 'all')->value(),
        ];

        $paginatedUsers = $query->handle($filters);

        $users = $paginatedUsers->through(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'email_verified_at' => $user->email_verified_at?->toISOString(),
            'roles' => $user->getRoleNames()->toArray(),
            'created_at' => $user->created_at->toISOString(),
            'updated_at' => $user->updated_at->toISOString(),
        ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $filters,
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('users/create', [
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $action): RedirectResponse
    {
        $action->handle($request->toData());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User created successfully.'),
        ]);

        return to_route('users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('users/edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_active' => $user->is_active,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'roles' => $user->getRoleNames()->toArray(),
                'created_at' => $user->created_at->toISOString(),
                'updated_at' => $user->updated_at->toISOString(),
            ],
            'availableRoles' => $this->availableRoles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): RedirectResponse
    {
        $action->handle($user, $request->toData(), $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User updated successfully.'),
        ]);

        return to_route('users.index');
    }

    public function destroy(DeleteUserRequest $request, User $user, DeleteUserAction $action): RedirectResponse
    {
        $action->handle($user, $request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('User deleted successfully.'),
        ]);

        return to_route('users.index');
    }

    /**
     * @return list<string>
     */
    private function availableRoles(): array
    {
        if (class_exists(RoleModel::class)) {
            /** @var list<string> $roles */
            $roles = RoleModel::query()->pluck('name')->all();

            if (! empty($roles)) {
                return $roles;
            }
        }

        return RoleEnum::values();
    }
}
