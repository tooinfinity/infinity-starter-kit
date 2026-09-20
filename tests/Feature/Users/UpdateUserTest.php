<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('renders edit user page for authorized user', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('users.edit', $target));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/edit')
            ->where('user.id', $target->id));
});

it('updates user details and role assignments', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    RoleModel::findOrCreate('manager');

    $target = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $response = $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'Updated Name',
            'email' => 'old@example.com',
            'is_active' => true,
            'roles' => ['manager'],
        ]);

    $response->assertRedirectToRoute('users.index');

    $target->refresh();
    expect($target->name)->toBe('Updated Name')
        ->and($target->hasRole('manager'))->toBeTrue();
});

it('allows keeping same email during update', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create(['email' => 'same@example.com']);

    $response = $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'Same Email',
            'email' => 'same@example.com',
        ]);

    $response->assertSessionHasNoErrors();
});

it('prevents user from deactivating their own account', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($admin)
        ->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'is_active' => false,
            'roles' => [],
        ]);

    $response->assertSessionHasErrors(['is_active' => __('You cannot deactivate your own account.')]);
});

it('prevents deactivating the last super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($admin)
        ->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'is_active' => false,
            'roles' => [Role::SuperAdmin->value],
        ]);

    $response->assertSessionHasErrors(['is_active' => __('Cannot deactivate the last Super Admin.')]);
});

it('prevents removing super admin role from the last super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $editorRole = RoleModel::findOrCreate('editor');
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($admin)
        ->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'is_active' => true,
            'roles' => ['editor'],
        ]);

    $response->assertSessionHasErrors(['roles' => __('Cannot remove the Super Admin role from the last Super Admin.')]);
});
