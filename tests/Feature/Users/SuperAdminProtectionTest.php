<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('prevents removing super admin role from last super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('editor');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $anotherAdmin = User::factory()->create();
    $anotherAdmin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $anotherAdmin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($anotherAdmin)
        ->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'is_active' => true,
            'roles' => ['editor'],
        ]);

    $response->assertSessionHasErrors(['roles']);

    expect($superAdmin->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('allows removing super admin role if multiple super admins exist', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('editor');

    $superAdmin1 = User::factory()->create();
    $superAdmin1->assignRole($superAdminRole);

    $superAdmin2 = User::factory()->create();
    $superAdmin2->assignRole($superAdminRole);

    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($admin)
        ->put(route('users.update', $superAdmin1), [
            'name' => $superAdmin1->name,
            'email' => $superAdmin1->email,
            'is_active' => true,
            'roles' => ['editor'],
        ]);

    $response->assertRedirectToRoute('users.index');
    expect($superAdmin1->fresh()->hasRole(Role::SuperAdmin->value))->toBeFalse()
        ->and($superAdmin1->fresh()->hasRole('editor'))->toBeTrue();
});
