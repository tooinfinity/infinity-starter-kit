<?php

declare(strict_types=1);

use App\Actions\Users\EnsureNotLastSuperAdminAction;
use App\Actions\Users\UpdateUserRolesAction;
use App\Enums\Role;
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as RoleModel;

it('prevents removing super admin role from last super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('editor');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $response = $this->actingAs($superAdmin)
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

    $response = $this->actingAs($superAdmin2)
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

it('EnsureNotLastSuperAdminAction throws validation exception when only one super admin exists', function (SuperAdminRestrictionOperation $operation): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $action = resolve(EnsureNotLastSuperAdminAction::class);

    DB::transaction(function () use ($action, $superAdmin, $operation): void {
        $action->handle($superAdmin, $operation);
    });
})->with([
    SuperAdminRestrictionOperation::Delete,
    SuperAdminRestrictionOperation::Deactivate,
    SuperAdminRestrictionOperation::RemoveRole,
])->throws(ValidationException::class);

it('EnsureNotLastSuperAdminAction passes when multiple super admins exist', function (SuperAdminRestrictionOperation $operation): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $superAdmin1 = User::factory()->create();
    $superAdmin1->assignRole($superAdminRole);

    $superAdmin2 = User::factory()->create();
    $superAdmin2->assignRole($superAdminRole);

    $action = resolve(EnsureNotLastSuperAdminAction::class);

    DB::transaction(function () use ($action, $superAdmin1, $operation): void {
        $action->handle($superAdmin1, $operation);
    });

    expect(true)->toBeTrue();
})->with([
    SuperAdminRestrictionOperation::Delete,
    SuperAdminRestrictionOperation::Deactivate,
    SuperAdminRestrictionOperation::RemoveRole,
]);

it('UpdateUserRolesAction protects last super admin when mutating roles directly', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('editor');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $action = resolve(UpdateUserRolesAction::class);

    expect(fn () => $action->handle($superAdmin, ['editor']))
        ->toThrow(ValidationException::class);

    expect($superAdmin->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('UpdateUserRolesAction allows removing super admin role if multiple super admins exist', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('editor');

    $superAdmin1 = User::factory()->create();
    $superAdmin1->assignRole($superAdminRole);

    $superAdmin2 = User::factory()->create();
    $superAdmin2->assignRole($superAdminRole);

    $action = resolve(UpdateUserRolesAction::class);

    $result = $action->handle($superAdmin1, ['editor']);

    expect($result->id)->toBe($superAdmin1->id)
        ->and($superAdmin1->fresh()->hasRole(Role::SuperAdmin->value))->toBeFalse()
        ->and($superAdmin1->fresh()->hasRole('editor'))->toBeTrue();
});

it('UpdateUserRolesAction updates roles and records audit trail by default', function (): void {
    RoleModel::findOrCreate('editor');
    RoleModel::findOrCreate('manager');

    $user = User::factory()->create();
    $user->assignRole('editor');

    $action = resolve(UpdateUserRolesAction::class);

    $result = $action->handle($user, ['manager']);

    expect($result->id)->toBe($user->id)
        ->and($user->fresh()->hasRole('manager'))->toBeTrue()
        ->and($user->fresh()->hasRole('editor'))->toBeFalse();
});

it('UpdateUserRolesAction updates roles without recording audit trail when disabled', function (): void {
    RoleModel::findOrCreate('editor');
    RoleModel::findOrCreate('manager');

    $user = User::factory()->create();
    $user->assignRole('editor');

    $action = resolve(UpdateUserRolesAction::class);

    $result = $action->handle($user, ['manager'], recordAuditTrail: false);

    expect($result->id)->toBe($user->id)
        ->and($user->fresh()->hasRole('manager'))->toBeTrue()
        ->and($user->fresh()->hasRole('editor'))->toBeFalse();
});

it('UpdateUserRolesAction allows assigning super admin role', function (): void {
    RoleModel::findOrCreate(Role::SuperAdmin->value);

    $user = User::factory()->create();

    $action = resolve(UpdateUserRolesAction::class);

    $result = $action->handle($user, [Role::SuperAdmin->value]);

    expect($result->id)->toBe($user->id)
        ->and($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('UpdateUserRolesAction returns unpersisted user directly', function (): void {
    $unsavedUser = new User;

    $action = resolve(UpdateUserRolesAction::class);

    $result = $action->handle($unsavedUser, ['editor']);

    expect($result)->toBe($unsavedUser);
});
