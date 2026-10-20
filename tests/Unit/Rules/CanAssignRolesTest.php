<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use App\Rules\CanAssignRoles;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

test('CanAssignRoles allows super admin to assign any role', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $actor = User::factory()->create();
    $actor->assignRole($superAdminRole);

    $opsRole = RoleModel::findOrCreate('ops');
    $opsRole->givePermissionTo(PermissionModel::findOrCreate(Permission::SettingsManage->value));

    $rule = new CanAssignRoles($actor);

    $failed = false;
    $rule->validate('roles', ['ops'], function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

test('CanAssignRoles rejects role with permissions actor lacks', function (): void {
    $opsRole = RoleModel::findOrCreate('ops');
    $opsRole->givePermissionTo(PermissionModel::findOrCreate(Permission::SettingsManage->value));

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $rule = new CanAssignRoles($actor);

    $failedMessage = null;
    $rule->validate('roles', ['ops'], function (string $message) use (&$failedMessage): void {
        $failedMessage = $message;
    });

    expect($failedMessage)->toBe(__('You cannot assign a role with permissions you do not have.'));
});

test('CanAssignRoles allows role whose permissions are a subset of actor permissions', function (): void {
    $viewerRole = RoleModel::findOrCreate('viewer');
    $viewerRole->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $rule = new CanAssignRoles($actor);

    $failed = false;
    $rule->validate('roles', ['viewer'], function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

test('CanAssignRoles allows keeping existing role on target even if actor lacks its permissions', function (): void {
    $billingRole = RoleModel::findOrCreate('billing');
    $billingRole->givePermissionTo(PermissionModel::findOrCreate(Permission::SettingsManage->value));

    $target = User::factory()->create();
    $target->assignRole($billingRole);

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $rule = new CanAssignRoles($actor, $target);

    $failed = false;
    $rule->validate('roles', ['billing'], function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

test('CanAssignRoles rejects non-super-admin assigning super admin role', function (): void {
    RoleModel::findOrCreate(Role::SuperAdmin->value);

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $rule = new CanAssignRoles($actor);

    $failedMessage = null;
    $rule->validate('roles', [Role::SuperAdmin->value], function (string $message) use (&$failedMessage): void {
        $failedMessage = $message;
    });

    expect($failedMessage)->toBe(__('Only Super Admins can assign the Super Admin role.'));
});

test('CanAssignRoles rejects non-super-admin removing super admin role', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('viewer');

    $target = User::factory()->create();
    $target->assignRole($superAdminRole);

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $rule = new CanAssignRoles($actor, $target);

    $failedMessage = null;
    $rule->validate('roles', ['viewer'], function (string $message) use (&$failedMessage): void {
        $failedMessage = $message;
    });

    expect($failedMessage)->toBe(__('Only Super Admins can remove the Super Admin role.'));
});

test('CanAssignRoles returns early if actor is null', function (): void {
    $rule = new CanAssignRoles(null);

    $failed = false;
    $rule->validate('roles', ['ops'], function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

test('CanAssignRoles returns early if actor cannot manage roles', function (): void {
    $actor = User::factory()->create();

    $rule = new CanAssignRoles($actor);

    $failed = false;
    $rule->validate('roles', ['ops'], function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

test('CanAssignRoles returns early if no roles need checking', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $rule = new CanAssignRoles($actor);

    $failed = false;
    $rule->validate('roles', [], function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

test('CanAssignRoles handles non-array value gracefully', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $rule = new CanAssignRoles($actor);

    $failed = false;
    $rule->validate('roles', 'not-an-array', function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});
