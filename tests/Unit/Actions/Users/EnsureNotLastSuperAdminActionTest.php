<?php

declare(strict_types=1);

use App\Actions\Users\EnsureNotLastSuperAdminAction;
use App\Enums\Role;
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as RoleModel;

test('EnsureNotLastSuperAdminAction passes if user is not super admin', function (): void {
    $user = User::factory()->create();

    $action = resolve(EnsureNotLastSuperAdminAction::class);
    $action->handle($user, SuperAdminRestrictionOperation::Delete);

    expect(true)->toBeTrue();
});

test('EnsureNotLastSuperAdminAction throws validation exception when deactivating last super admin', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user = User::factory()->create();
    $user->assignRole($role);

    $action = resolve(EnsureNotLastSuperAdminAction::class);

    expect(fn () => $action->handle($user, SuperAdminRestrictionOperation::Deactivate))
        ->toThrow(ValidationException::class);
});

test('EnsureNotLastSuperAdminAction throws validation exception when deleting last super admin', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user = User::factory()->create();
    $user->assignRole($role);

    $action = resolve(EnsureNotLastSuperAdminAction::class);

    expect(fn () => $action->handle($user, SuperAdminRestrictionOperation::Delete))
        ->toThrow(ValidationException::class);
});

test('EnsureNotLastSuperAdminAction throws validation exception when removing role from last super admin', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user = User::factory()->create();
    $user->assignRole($role);

    $action = resolve(EnsureNotLastSuperAdminAction::class);

    expect(fn () => $action->handle($user, SuperAdminRestrictionOperation::RemoveRole))
        ->toThrow(ValidationException::class);
});

test('EnsureNotLastSuperAdminAction passes when multiple super admins exist', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user1 = User::factory()->create();
    $user1->assignRole($role);

    $user2 = User::factory()->create();
    $user2->assignRole($role);

    $action = resolve(EnsureNotLastSuperAdminAction::class);
    $action->handle($user1, SuperAdminRestrictionOperation::Delete);

    expect(true)->toBeTrue();
});
