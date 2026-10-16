<?php

declare(strict_types=1);

namespace App\Http\Controllers\Users;

use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Data\Users\UserData;
use App\Http\Requests\Users\DeleteUserRequest;
use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use App\Queries\Users\UserListingQuery;
use App\Queries\Users\UserRolesQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class UserController
{
    public function index(Request $request, UserListingQuery $query, UserRolesQuery $rolesQuery): Response
    {
        $filters = [
            'search' => $request->string('search')->value(),
            'status' => $request->string('status', 'all')->value(),
        ];

        $paginatedUsers = $query->handle($filters);

        $users = $paginatedUsers->through(fn (User $user): array => UserData::fromModel($user)->toArray());

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $filters,
            'availableRoles' => $rolesQuery->handle(),
        ]);
    }

    public function create(UserRolesQuery $rolesQuery): Response
    {
        return Inertia::render('users/create', [
            'availableRoles' => $rolesQuery->handle(),
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

    public function edit(User $user, UserRolesQuery $rolesQuery): Response
    {
        return Inertia::render('users/edit', [
            'user' => UserData::fromModel($user)->toArray(),
            'availableRoles' => $rolesQuery->handle(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $action): RedirectResponse
    {
        $action->handle($user, $request->toData());

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
}
