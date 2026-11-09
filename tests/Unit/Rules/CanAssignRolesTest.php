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

test('CanAssignRoles translates error messages in French and Arabic', function (string $locale, string $expectedAssignMsg, string $expectedRemoveMsg, string $expectedPermMsg): void {
    app()->setLocale($locale);

    RoleModel::findOrCreate(Role::SuperAdmin->value);
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $assignRule = new CanAssignRoles($actor);
    $assignFailedMessage = null;
    $assignRule->validate('roles', [Role::SuperAdmin->value], function (string $message) use (&$assignFailedMessage): void {
        $assignFailedMessage = $message;
    });
    expect($assignFailedMessage)->toBe($expectedAssignMsg);

    $target = User::factory()->create();
    $target->assignRole(Role::SuperAdmin->value);

    $removeRule = new CanAssignRoles($actor, $target);
    $removeFailedMessage = null;
    $removeRule->validate('roles', [], function (string $message) use (&$removeFailedMessage): void {
        $removeFailedMessage = $message;
    });
    expect($removeFailedMessage)->toBe($expectedRemoveMsg);

    $opsRole = RoleModel::findOrCreate('ops');
    $opsRole->givePermissionTo(PermissionModel::findOrCreate(Permission::SettingsManage->value));

    $permRule = new CanAssignRoles($actor);
    $permFailedMessage = null;
    $permRule->validate('roles', ['ops'], function (string $message) use (&$permFailedMessage): void {
        $permFailedMessage = $message;
    });
    expect($permFailedMessage)->toBe($expectedPermMsg);
})->with([
    ['en', 'Only Super Admins can assign the Super Admin role.', 'Only Super Admins can remove the Super Admin role.', 'You cannot assign a role with permissions you do not have.'],
    ['fr', 'Seuls les super administrateurs peuvent attribuer le rôle de super administrateur.', 'Seuls les super administrateurs peuvent retirer le rôle de super administrateur.', 'Vous ne pouvez pas attribuer un rôle avec des autorisations que vous ne possédez pas.'],
    ['ar', 'يمكن للمسؤولين المتميزين فقط تعيين دور المسؤول المتميز.', 'يمكن للمسؤولين المتميزين فقط إزالة دور المسؤول المتميز.', 'لا يمكنك تعيين دور بصلاحيات لا تملكها.'],
]);
