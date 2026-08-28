<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

/* @chisel-roles-permissions */

test('super admin bypasses all gate checks', function (): void {
    $user = User::factory()->create();
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user->assignRole($superAdminRole);

    expect(Gate::forUser($user)->allows('non-existent-permission'))->toBeTrue();
});

test('normal user is denied without required permission', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::UsersView->value);

    expect(Gate::forUser($user)->allows(Permission::UsersView->value))->toBeFalse();
});

test('user with permission is authorized via gate', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::UsersCreate->value);
    $user->givePermissionTo($permission);

    expect(Gate::forUser($user)->allows(Permission::UsersCreate->value))->toBeTrue();
});

test('user with role permission is authorized via gate', function (): void {
    $user = User::factory()->create();
    $role = RoleModel::findOrCreate('editor');
    $permission = PermissionModel::findOrCreate(Permission::UsersUpdate->value);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    expect(Gate::forUser($user)->allows(Permission::UsersUpdate->value))->toBeTrue();
});

test('authorization setup command creates permissions and roles', function (): void {
    $this->artisan('authorization:setup')
        ->assertSuccessful();

    foreach (Permission::values() as $permissionName) {
        expect(PermissionModel::findByName($permissionName))->not->toBeNull();
    }

    $superAdmin = RoleModel::findByName(Role::SuperAdmin->value);
    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->permissions)->toHaveCount(count(Permission::cases()));
});

test('authorization setup command is idempotent', function (): void {
    $this->artisan('authorization:setup')->assertSuccessful();
    $this->artisan('authorization:setup')->assertSuccessful();

    expect(PermissionModel::all())->toHaveCount(count(Permission::cases()));
    expect(RoleModel::query()->where('name', Role::SuperAdmin->value)->count())->toBe(1);
});

test('authorization setup command synchronizes permissions on super admin role', function (): void {
    $this->artisan('authorization:setup')->assertSuccessful();

    $superAdmin = RoleModel::findByName(Role::SuperAdmin->value);

    expect($superAdmin->permissions->pluck('name')->sort()->values()->toArray())
        ->toBe(collect(Permission::values())->sort()->values()->all());
});

test('shared inertia props include authorization data for authenticated user', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::UsersView->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('auth.permissions')
            ->has('auth.roles')
            ->where('auth.permissions', [Permission::UsersView->value])
        );
});

test('shared inertia props return empty arrays for guests', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', [])
            ->where('auth.roles', [])
        );
});

/* @end-chisel-roles-permissions */
