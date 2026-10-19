<?php

declare(strict_types=1);

use App\Actions\Users\EnsureActorMayManageTargetAction;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Models\Role as RoleModel;

test('EnsureActorMayManageTargetAction returns true if target is not a super admin', function (): void {
    $action = resolve(EnsureActorMayManageTargetAction::class);

    $actor = User::factory()->create();
    $target = User::factory()->create();

    expect($action->handle($actor, $target))->toBeTrue();
});

test('EnsureActorMayManageTargetAction returns true if target is super admin and actor is super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);

    $actor = User::factory()->create();
    $actor->assignRole($superAdminRole);

    $target = User::factory()->create();
    $target->assignRole($superAdminRole);

    $action = resolve(EnsureActorMayManageTargetAction::class);

    expect($action->handle($actor, $target))->toBeTrue();
});

test('EnsureActorMayManageTargetAction returns false if target is super admin and actor is not super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);

    $actor = User::factory()->create();
    $target = User::factory()->create();
    $target->assignRole($superAdminRole);

    $action = resolve(EnsureActorMayManageTargetAction::class);

    expect($action->handle($actor, $target))->toBeFalse();
});

test('EnsureActorMayManageTargetAction ensure throws AuthorizationException when actor is not super admin and target is super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);

    $actor = User::factory()->create();
    $target = User::factory()->create();
    $target->assignRole($superAdminRole);

    $action = resolve(EnsureActorMayManageTargetAction::class);

    expect(fn () => $action->ensure($actor, $target))
        ->toThrow(AuthorizationException::class);
});
