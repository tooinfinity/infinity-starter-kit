<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Http\Requests\Users\ActivateUserRequest;
use App\Http\Requests\Users\DeactivateUserRequest;
use App\Http\Requests\Users\DeleteUserRequest;
use App\Http\Requests\Users\EditUserRequest;
use App\Http\Requests\Users\UpdateUserPasswordRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission as PermissionModel;

dataset('user-management-requests', [
    'activate' => [ActivateUserRequest::class, Permission::UsersUpdate, 'PATCH', 'users/{user}/activate'],
    'deactivate' => [DeactivateUserRequest::class, Permission::UsersUpdate, 'PATCH', 'users/{user}/deactivate'],
    'delete' => [DeleteUserRequest::class, Permission::UsersDelete, 'DELETE', 'users/{user}'],
    'edit user' => [EditUserRequest::class, Permission::UsersUpdate, 'GET', 'users/{user}/edit'],
    'update password' => [UpdateUserPasswordRequest::class, Permission::UsersManagePassword, 'PUT', 'users/{user}/password'],
    'update user' => [UpdateUserRequest::class, Permission::UsersUpdate, 'PUT', 'users/{user}'],
]);

it('denies authorization when actor is not an authenticated User instance', function (string $requestClass, Permission $permission, string $method, string $uri): void {
    /** @var FormRequest $request */
    $request = new $requestClass;
    $request->setUserResolver(fn (): null => null);

    expect($request->authorize())->toBeFalse();
})->with('user-management-requests');

it('resolves string target ID and allows authorized user', function (string $requestClass, Permission $permission, string $method, string $uri): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate($permission->value));
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create();

    /** @var FormRequest $request */
    $request = new $requestClass;
    $request->setUserResolver(fn () => $actor);

    $route = new Route($method, $uri, []);
    $route->bind(request());
    $route->setParameter('user', (string) $target->id);

    $request->setRouteResolver(fn (): Route => $route);

    expect($request->authorize())->toBeTrue();
})->with('user-management-requests');

it('denies authorization when string target ID does not exist', function (string $requestClass, Permission $permission, string $method, string $uri): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate($permission->value));

    /** @var FormRequest $request */
    $request = new $requestClass;
    $request->setUserResolver(fn () => $actor);

    $route = new Route($method, $uri, []);
    $route->bind(request());
    $route->setParameter('user', (string) Str::uuid());

    $request->setRouteResolver(fn (): Route => $route);

    expect($request->authorize())->toBeFalse();
})->with('user-management-requests');

it('denies authorization when string target ID is an invalid uuid', function (string $requestClass, Permission $permission, string $method, string $uri): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate($permission->value));

    /** @var FormRequest $request */
    $request = new $requestClass;
    $request->setUserResolver(fn () => $actor);

    $route = new Route($method, $uri, []);
    $route->bind(request());
    $route->setParameter('user', 'non-existent-uuid');

    $request->setRouteResolver(fn (): Route => $route);

    expect($request->authorize())->toBeFalse();
})->with('user-management-requests');

it('requires both user view and operation permissions on management routes', function (string $method, string $routeName, Permission $operationPermission, bool $grantView): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();
    $permission = $grantView ? Permission::UsersView : $operationPermission;
    $actor->givePermissionTo(PermissionModel::findOrCreate($permission->value));

    $this->actingAs($actor)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->call($method, route($routeName, $routeName === 'users.store' ? [] : ['user' => $target]))
        ->assertForbidden();
})->with([
    'store without view' => ['POST', 'users.store', Permission::UsersCreate, false],
    'store without create' => ['POST', 'users.store', Permission::UsersCreate, true],
    'edit without view' => ['GET', 'users.edit', Permission::UsersUpdate, false],
    'edit without update' => ['GET', 'users.edit', Permission::UsersUpdate, true],
    'update without view' => ['PUT', 'users.update', Permission::UsersUpdate, false],
    'update without update' => ['PUT', 'users.update', Permission::UsersUpdate, true],
    'delete without view' => ['DELETE', 'users.destroy', Permission::UsersDelete, false],
    'delete without delete' => ['DELETE', 'users.destroy', Permission::UsersDelete, true],
    'activate without view' => ['PATCH', 'users.activate', Permission::UsersUpdate, false],
    'activate without update' => ['PATCH', 'users.activate', Permission::UsersUpdate, true],
    'deactivate without view' => ['PATCH', 'users.deactivate', Permission::UsersUpdate, false],
    'deactivate without update' => ['PATCH', 'users.deactivate', Permission::UsersUpdate, true],
    'password without view' => ['PUT', 'users.password.update', Permission::UsersManagePassword, false],
    'password without password permission' => ['PUT', 'users.password.update', Permission::UsersManagePassword, true],
]);
