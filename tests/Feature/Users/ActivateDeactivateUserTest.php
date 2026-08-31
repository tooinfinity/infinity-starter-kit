<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('activates an inactive user', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $inactiveUser = User::factory()->inactive()->create();

    $response = $this->actingAs($admin)
        ->patch(route('users.activate', $inactiveUser));

    $response->assertRedirect();

    expect($inactiveUser->fresh()->is_active)->toBeTrue();
});

it('deactivates an active user', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $activeUser = User::factory()->active()->create();

    $response = $this->actingAs($admin)
        ->patch(route('users.deactivate', $activeUser));

    $response->assertRedirect();

    expect($activeUser->fresh()->is_active)->toBeFalse();
});

it('prevents user from deactivating themselves', function (): void {
    $admin = User::factory()->active()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($admin)
        ->patch(route('users.deactivate', $admin));

    $response->assertSessionHasErrors(['user']);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('prevents deactivating the last super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $superAdmin = User::factory()->active()->create();
    $superAdmin->assignRole($superAdminRole);

    $anotherAdmin = User::factory()->create();
    $anotherAdmin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $anotherAdmin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($anotherAdmin)
        ->patch(route('users.deactivate', $superAdmin));

    $response->assertSessionHasErrors(['user']);

    expect($superAdmin->fresh()->is_active)->toBeTrue();
});
