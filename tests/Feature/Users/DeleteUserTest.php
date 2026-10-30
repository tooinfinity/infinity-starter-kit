<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
/* @chisel-notifications */
use App\Notifications\UserActivated;
use Illuminate\Notifications\DatabaseNotification;
/* @end-chisel-notifications */
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('deletes user when authorized', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersDelete->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create();

    $response = $this->actingAs($admin)
        ->delete(route('users.destroy', $target));

    $response->assertRedirectToRoute('users.index');

    expect(User::query()->where('id', $target->id)->exists())->toBeFalse();
});

it('prevents user from deleting themselves', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersDelete->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($admin)
        ->delete(route('users.destroy', $admin));

    $response->assertSessionHasErrors(['user']);

    expect(User::query()->where('id', $admin->id)->exists())->toBeTrue();
});

it('forbids non-super-admins from deleting a super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $anotherAdmin = User::factory()->create();
    $anotherAdmin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersDelete->value));
    $anotherAdmin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($anotherAdmin)
        ->delete(route('users.destroy', $superAdmin));

    $response->assertForbidden();

    expect(User::query()->where('id', $superAdmin->id)->exists())->toBeTrue();
});

it('forbids unauthorized users from deleting another user', function (): void {
    $unauthorizedUser = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($unauthorizedUser)
        ->delete(route('users.destroy', $target))
        ->assertForbidden();

    expect(User::query()->where('id', $target->id)->exists())->toBeTrue();
});

/* @chisel-notifications */
it('prunes notifications when user is deleted', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersDelete->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create();

    $target->notify(new UserActivated);

    expect($target->notifications()->count())->toBe(1);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirectToRoute('users.index');

    expect(User::query()->where('id', $target->id)->exists())->toBeFalse();

    expect(DatabaseNotification::query()->where('notifiable_id', $target->id)->count())->toBe(0);
});
/* @end-chisel-notifications */
