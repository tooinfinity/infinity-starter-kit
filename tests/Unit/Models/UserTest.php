<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Role;

test('to array', function (): void {
    $user = User::factory()->create()->refresh();

    expect(array_keys($user->toArray()))
        ->toBe([
            'id',
            'name',
            'email',
            /* @chisel-email-verification */
            'email_verified_at',
            /* @end-chisel-email-verification */
            /* @chisel-two-factor */
            'two_factor_confirmed_at',
            /* @end-chisel-two-factor */
            /* @chisel-user-management */
            'is_active',
            /* @end-chisel-user-management */
            /* @chisel-localization */
            'locale',
            /* @end-chisel-localization */
            'created_at',
            'updated_at',
        ]);
});

/* @chisel-user-management */
test('canManage returns true if target is not a super admin', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    expect($actor->canManage($target))->toBeTrue();
});

test('canManage returns true if target is super admin and actor is super admin', function (): void {
    $superAdminRole = Role::findOrCreate(App\Enums\Role::SuperAdmin->value);

    $actor = User::factory()->create();
    $actor->assignRole($superAdminRole);

    $target = User::factory()->create();
    $target->assignRole($superAdminRole);

    expect($actor->canManage($target))->toBeTrue();
});

test('canManage returns false if target is super admin and actor is not super admin', function (): void {
    $superAdminRole = Role::findOrCreate(App\Enums\Role::SuperAdmin->value);

    $actor = User::factory()->create();
    $target = User::factory()->create();
    $target->assignRole($superAdminRole);

    expect($actor->canManage($target))->toBeFalse();
});
/* @end-chisel-user-management */
