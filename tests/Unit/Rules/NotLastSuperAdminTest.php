<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use App\Rules\NotLastSuperAdmin;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as RoleModel;

test('NotLastSuperAdmin passes if user is not super admin', function (): void {
    $user = User::factory()->create();

    $rule = new NotLastSuperAdmin($user, SuperAdminRestrictionOperation::Delete);

    $failed = false;
    $rule->validate('user', $user, function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

test('NotLastSuperAdmin fails when deactivating last super admin', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user = User::factory()->create();
    $user->assignRole($role);

    $rule = new NotLastSuperAdmin($user, SuperAdminRestrictionOperation::Deactivate);

    $failedMessage = null;
    $rule->validate('user', $user, function (string $message) use (&$failedMessage): void {
        $failedMessage = $message;
    });

    expect($failedMessage)->toBe(__('Cannot deactivate the last Super Admin.'));
});

test('NotLastSuperAdmin fails when deleting last super admin', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user = User::factory()->create();
    $user->assignRole($role);

    $rule = new NotLastSuperAdmin($user, SuperAdminRestrictionOperation::Delete);

    $failedMessage = null;
    $rule->validate('user', $user, function (string $message) use (&$failedMessage): void {
        $failedMessage = $message;
    });

    expect($failedMessage)->toBe(__('Cannot delete the last Super Admin.'));
});

test('NotLastSuperAdmin fails when removing role from last super admin', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user = User::factory()->create();
    $user->assignRole($role);

    $rule = new NotLastSuperAdmin($user, SuperAdminRestrictionOperation::RemoveRole);

    $failedMessage = null;
    $rule->validate('roles', ['editor'], function (string $message) use (&$failedMessage): void {
        $failedMessage = $message;
    });

    expect($failedMessage)->toBe(__('Cannot remove the Super Admin role from the last Super Admin.'));
});

test('NotLastSuperAdmin passes when multiple super admins exist', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user1 = User::factory()->create();
    $user1->assignRole($role);

    $user2 = User::factory()->create();
    $user2->assignRole($role);

    $rule = new NotLastSuperAdmin($user1, SuperAdminRestrictionOperation::Delete);

    $failed = false;
    $rule->validate('user', $user1, function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

test('NotLastSuperAdmin static check throws validation exception when only one super admin exists', function (SuperAdminRestrictionOperation $operation): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user = User::factory()->create();
    $user->assignRole($role);

    NotLastSuperAdmin::check($user, $operation);
})->with([
    SuperAdminRestrictionOperation::Delete,
    SuperAdminRestrictionOperation::Deactivate,
    SuperAdminRestrictionOperation::RemoveRole,
])->throws(ValidationException::class);

test('NotLastSuperAdmin check returns early when user is not super admin', function (): void {
    $user = User::factory()->create();

    NotLastSuperAdmin::check($user, SuperAdminRestrictionOperation::Delete);

    expect(true)->toBeTrue();
});

test('NotLastSuperAdmin validate returns early if user is null', function (): void {
    $rule = new NotLastSuperAdmin(null);

    $failed = false;
    $rule->validate('user', null, function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

test('NotLastSuperAdmin validate passes when super admin role is retained', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user = User::factory()->create();
    $user->assignRole($role);

    $rule = new NotLastSuperAdmin($user, SuperAdminRestrictionOperation::RemoveRole);

    $failed = false;
    $rule->validate('roles', [Role::SuperAdmin->value, 'editor'], function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeFalse();
});

test('NotLastSuperAdmin validateRequest returns early when user is null or not super admin', function (): void {
    $validator = Validator::make([], []);

    NotLastSuperAdmin::validateRequest(null, SuperAdminRestrictionOperation::Deactivate, $validator);
    expect($validator->errors()->isEmpty())->toBeTrue();

    $user = User::factory()->create();
    NotLastSuperAdmin::validateRequest($user, SuperAdminRestrictionOperation::Deactivate, $validator);
    expect($validator->errors()->isEmpty())->toBeTrue();
});

test('NotLastSuperAdmin validateRequest adds error when last super admin', function (SuperAdminRestrictionOperation $operation): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user = User::factory()->create();
    $user->assignRole($role);

    $validator = Validator::make([], []);
    NotLastSuperAdmin::validateRequest($user, $operation, $validator);

    expect($validator->errors()->has('user'))->toBeTrue();
})->with([
    SuperAdminRestrictionOperation::Delete,
    SuperAdminRestrictionOperation::Deactivate,
    SuperAdminRestrictionOperation::RemoveRole,
]);

test('NotLastSuperAdmin validateRequest passes when multiple super admins exist', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user1 = User::factory()->create();
    $user1->assignRole($role);

    $user2 = User::factory()->create();
    $user2->assignRole($role);

    $validator = Validator::make([], []);
    NotLastSuperAdmin::validateRequest($user1, SuperAdminRestrictionOperation::Deactivate, $validator);

    expect($validator->errors()->isEmpty())->toBeTrue();
});

test('NotLastSuperAdmin validate fails when only one active super admin exists and an inactive one exists', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user1 = User::factory()->create();
    $user1->assignRole($role);

    $user2 = User::factory()->create(['is_active' => false]);
    $user2->assignRole($role);

    $rule = new NotLastSuperAdmin($user1, SuperAdminRestrictionOperation::Delete);

    $failed = false;
    $rule->validate('user', $user1, function () use (&$failed): void {
        $failed = true;
    });

    expect($failed)->toBeTrue();
});

test('NotLastSuperAdmin check throws when only one active super admin exists and an inactive one exists', function (SuperAdminRestrictionOperation $operation): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user1 = User::factory()->create();
    $user1->assignRole($role);

    $user2 = User::factory()->create(['is_active' => false]);
    $user2->assignRole($role);

    NotLastSuperAdmin::check($user1, $operation);
})->with([
    SuperAdminRestrictionOperation::Delete,
    SuperAdminRestrictionOperation::Deactivate,
    SuperAdminRestrictionOperation::RemoveRole,
])->throws(ValidationException::class);

test('NotLastSuperAdmin validateRequest adds error when only one active super admin exists and an inactive one exists', function (SuperAdminRestrictionOperation $operation): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user1 = User::factory()->create();
    $user1->assignRole($role);

    $user2 = User::factory()->create(['is_active' => false]);
    $user2->assignRole($role);

    $validator = Validator::make([], []);
    NotLastSuperAdmin::validateRequest($user1, $operation, $validator);

    expect($validator->errors()->has('user'))->toBeTrue();
})->with([
    SuperAdminRestrictionOperation::Delete,
    SuperAdminRestrictionOperation::Deactivate,
    SuperAdminRestrictionOperation::RemoveRole,
]);
