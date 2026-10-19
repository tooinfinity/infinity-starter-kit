<?php

declare(strict_types=1);

use App\Actions\Users\EnsureActorMayAssignRolesAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

test('EnsureActorMayAssignRolesAction allows super admin to assign any role', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $actor = User::factory()->create();
    $actor->assignRole($superAdminRole);

    $opsRole = RoleModel::findOrCreate('ops');
    $opsRole->givePermissionTo(PermissionModel::findOrCreate(Permission::SettingsManage->value));

    $action = resolve(EnsureActorMayAssignRolesAction::class);

    $validator = ValidatorFacade::make([], []);
    $action->handle($validator, $actor, ['ops']);

    expect($validator->errors()->isEmpty())->toBeTrue();
});

test('EnsureActorMayAssignRolesAction rejects role with permissions actor lacks', function (): void {
    $opsRole = RoleModel::findOrCreate('ops');
    $opsRole->givePermissionTo(PermissionModel::findOrCreate(Permission::SettingsManage->value));

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $action = resolve(EnsureActorMayAssignRolesAction::class);

    $validator = ValidatorFacade::make([], []);
    $action->handle($validator, $actor, ['ops']);

    expect($validator->errors()->has('roles'))->toBeTrue()
        ->and($validator->errors()->first('roles'))->toBe(__('You cannot assign a role with permissions you do not have.'));
});

test('EnsureActorMayAssignRolesAction allows role whose permissions are a subset of actor permissions', function (): void {
    $viewerRole = RoleModel::findOrCreate('viewer');
    $viewerRole->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $action = resolve(EnsureActorMayAssignRolesAction::class);

    $validator = ValidatorFacade::make([], []);
    $action->handle($validator, $actor, ['viewer']);

    expect($validator->errors()->isEmpty())->toBeTrue();
});

test('EnsureActorMayAssignRolesAction allows keeping existing role on target even if actor lacks its permissions', function (): void {
    $opsRole = RoleModel::findOrCreate('ops');
    $opsRole->givePermissionTo(PermissionModel::findOrCreate(Permission::SettingsManage->value));

    $target = User::factory()->create();
    $target->assignRole($opsRole);

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $action = resolve(EnsureActorMayAssignRolesAction::class);

    $validator = ValidatorFacade::make([], []);
    $action->handle($validator, $actor, ['ops'], $target);

    expect($validator->errors()->isEmpty())->toBeTrue();
});

test('EnsureActorMayAssignRolesAction rejects non-super-admin assigning super admin role', function (): void {
    RoleModel::findOrCreate(Role::SuperAdmin->value);

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $action = resolve(EnsureActorMayAssignRolesAction::class);

    $validator = ValidatorFacade::make([], []);
    $action->handle($validator, $actor, [Role::SuperAdmin->value]);

    expect($validator->errors()->first('roles'))->toBe(__('Only Super Admins can assign the Super Admin role.'));
});

test('EnsureActorMayAssignRolesAction rejects non-super-admin removing super admin role', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);

    $target = User::factory()->create();
    $target->assignRole($superAdminRole);

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $action = resolve(EnsureActorMayAssignRolesAction::class);

    $validator = ValidatorFacade::make([], []);
    $action->handle($validator, $actor, [], $target);

    expect($validator->errors()->first('roles'))->toBe(__('Only Super Admins can remove the Super Admin role.'));
});

test('EnsureActorMayAssignRolesAction ensure throws ValidationException on failure', function (): void {
    $opsRole = RoleModel::findOrCreate('ops');
    $opsRole->givePermissionTo(PermissionModel::findOrCreate(Permission::SettingsManage->value));

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $action = resolve(EnsureActorMayAssignRolesAction::class);

    expect(fn () => $action->ensure($actor, ['ops']))
        ->toThrow(ValidationException::class);
});

test('EnsureActorMayAssignRolesAction returns early if actor is null', function (): void {
    $action = resolve(EnsureActorMayAssignRolesAction::class);

    $validator = ValidatorFacade::make([], []);
    $action->handle($validator, null, ['ops']);

    expect($validator->errors()->isEmpty())->toBeTrue();
});

test('EnsureActorMayAssignRolesAction returns early if actor cannot manage roles', function (): void {
    $actor = User::factory()->create();

    $action = resolve(EnsureActorMayAssignRolesAction::class);

    $validator = ValidatorFacade::make([], []);
    $action->handle($validator, $actor, ['ops']);

    expect($validator->errors()->isEmpty())->toBeTrue();
});

test('EnsureActorMayAssignRolesAction returns early if no roles need checking', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $action = resolve(EnsureActorMayAssignRolesAction::class);

    $validator = ValidatorFacade::make([], []);
    $action->handle($validator, $actor, []);

    expect($validator->errors()->isEmpty())->toBeTrue();
});

test('EnsureActorMayAssignRolesAction ensure passes when assignment is valid', function (): void {
    $viewerRole = RoleModel::findOrCreate('viewer');
    $viewerRole->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $action = resolve(EnsureActorMayAssignRolesAction::class);

    $action->ensure($actor, ['viewer']);

    expect(true)->toBeTrue();
});
