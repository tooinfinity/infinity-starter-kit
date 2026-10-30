<?php

declare(strict_types=1);

use App\Actions\Users\UpdateUserRoles;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as RoleModel;

it('protects last super admin when mutating roles directly', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('editor');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $action = resolve(UpdateUserRoles::class);

    expect(fn () => $action->handle($superAdmin, ['editor']))
        ->toThrow(ValidationException::class);

    expect($superAdmin->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('allows removing super admin role if multiple super admins exist', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('editor');

    $superAdmin1 = User::factory()->create();
    $superAdmin1->assignRole($superAdminRole);

    $superAdmin2 = User::factory()->create();
    $superAdmin2->assignRole($superAdminRole);

    $action = resolve(UpdateUserRoles::class);

    $result = $action->handle($superAdmin1, ['editor']);

    expect($result->id)->toBe($superAdmin1->id)
        ->and($superAdmin1->fresh()->hasRole(Role::SuperAdmin->value))->toBeFalse()
        ->and($superAdmin1->fresh()->hasRole('editor'))->toBeTrue();
});

it('updates roles and records audit trail by default', function (): void {
    RoleModel::findOrCreate('editor');
    RoleModel::findOrCreate('manager');

    $user = User::factory()->create();
    $user->assignRole('editor');

    $action = resolve(UpdateUserRoles::class);

    $result = $action->handle($user, ['manager']);

    expect($result->id)->toBe($user->id)
        ->and($user->fresh()->hasRole('manager'))->toBeTrue()
        ->and($user->fresh()->hasRole('editor'))->toBeFalse();
});

it('updates roles without recording audit trail when disabled', function (): void {
    RoleModel::findOrCreate('editor');
    RoleModel::findOrCreate('manager');

    $user = User::factory()->create();
    $user->assignRole('editor');

    $action = resolve(UpdateUserRoles::class);

    $result = $action->handle($user, ['manager'], recordAuditTrail: false);

    expect($result->id)->toBe($user->id)
        ->and($user->fresh()->hasRole('manager'))->toBeTrue()
        ->and($user->fresh()->hasRole('editor'))->toBeFalse();
});

it('allows assigning super admin role', function (): void {
    RoleModel::findOrCreate(Role::SuperAdmin->value);

    $user = User::factory()->create();

    $action = resolve(UpdateUserRoles::class);

    $result = $action->handle($user, [Role::SuperAdmin->value]);

    expect($result->id)->toBe($user->id)
        ->and($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('returns unpersisted user directly', function (): void {
    $unsavedUser = new User;

    $action = resolve(UpdateUserRoles::class);

    $result = $action->handle($unsavedUser, ['editor']);

    expect($result)->toBe($unsavedUser);
});
