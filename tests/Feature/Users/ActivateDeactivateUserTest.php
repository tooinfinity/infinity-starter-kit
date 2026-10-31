<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
/* @chisel-notifications */
use App\Notifications\UserActivated;
use App\Notifications\UserDeactivated;
/* @end-chisel-notifications */
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('activates an inactive user', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $inactiveUser = User::factory()->inactive()->create();

    $response = $this->actingAs($admin)
        ->patch(route('users.activate', $inactiveUser));

    $response->assertRedirect();

    expect($inactiveUser->fresh()->is_active)->toBeTrue();

    /* @chisel-notifications */
    Notification::assertSentTo($inactiveUser, UserActivated::class);
    /* @end-chisel-notifications */
});

it('deactivates an active user', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $activeUser = User::factory()->active()->create();

    $response = $this->actingAs($admin)
        ->patch(route('users.deactivate', $activeUser));

    $response->assertRedirect();

    expect($activeUser->fresh()->is_active)->toBeFalse();

    /* @chisel-notifications */
    Notification::assertSentTo($activeUser, UserDeactivated::class);
    /* @end-chisel-notifications */
});

it('forbids unauthorized users from activating or deactivating another user', function (): void {
    $unauthorizedUser = User::factory()->create();
    $target = User::factory()->active()->create();

    $this->actingAs($unauthorizedUser)
        ->patch(route('users.deactivate', $target))
        ->assertForbidden();

    expect($target->fresh()->is_active)->toBeTrue();

    $target->forceFill(['is_active' => false])->save();

    $this->actingAs($unauthorizedUser)
        ->patch(route('users.activate', $target))
        ->assertForbidden();

    expect($target->fresh()->is_active)->toBeFalse();
});

it('prevents user from deactivating themselves', function (): void {
    $admin = User::factory()->active()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($admin)
        ->patch(route('users.deactivate', $admin));

    $response->assertSessionHasErrors(['user']);

    expect($admin->fresh()->is_active)->toBeTrue();
});

it('forbids non-super-admins from deactivating a super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $superAdmin = User::factory()->active()->create();
    $superAdmin->assignRole($superAdminRole);

    $anotherAdmin = User::factory()->create();
    $anotherAdmin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $anotherAdmin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($anotherAdmin)
        ->patch(route('users.deactivate', $superAdmin));

    $response->assertForbidden();

    expect($superAdmin->fresh()->is_active)->toBeTrue();
});
