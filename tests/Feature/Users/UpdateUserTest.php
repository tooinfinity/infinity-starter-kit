<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('renders edit user page for authorized user', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create();

    $response = $this->actingAs($admin)
        ->get(route('users.edit', $target));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/edit')
            ->where('user.id', $target->id));
});

it('updates user details and role assignments', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));
    RoleModel::findOrCreate('manager');

    $target = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $response = $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'Updated Name',
            'email' => 'old@example.com',
            'is_active' => true,
            'roles' => ['manager'],
        ]);

    $response->assertRedirectToRoute('users.index');

    $target->refresh();
    expect($target->name)->toBe('Updated Name')
        ->and($target->hasRole('manager'))->toBeTrue();
});

it('synchronizes roles through UpdateUserRolesAction during update', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));
    RoleModel::findOrCreate('manager');
    RoleModel::findOrCreate('editor');

    $target = User::factory()->create();
    $target->assignRole('editor');

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'Target User',
            'email' => $target->email,
            'roles' => ['manager'],
        ]);

    $target->refresh();
    expect($target->hasRole('manager'))->toBeTrue()
        ->and($target->hasRole('editor'))->toBeFalse();
});

it('allows keeping same email during update', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create(['email' => 'same@example.com']);

    $response = $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'Same Email',
            'email' => 'same@example.com',
        ]);

    $response->assertSessionHasNoErrors();
});

it('cannot deactivate an active user through normal profile update', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->active()->create();

    $response = $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'Updated Name',
            'email' => $target->email,
            'is_active' => false,
        ]);

    $response->assertRedirectToRoute('users.index');

    expect($target->fresh()->is_active)->toBeTrue();
});

it('cannot activate an inactive user through normal profile update', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->inactive()->create();

    $response = $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'Updated Name',
            'email' => $target->email,
            'is_active' => true,
        ]);

    $response->assertRedirectToRoute('users.index');

    expect($target->fresh()->is_active)->toBeFalse();
});

it('forbids unauthorized users from updating another user', function (): void {
    $unauthorizedUser = User::factory()->create();
    $target = User::factory()->create();

    $response = $this->actingAs($unauthorizedUser)
        ->put(route('users.update', $target), [
            'name' => 'Updated Name',
            'email' => 'new@example.com',
        ]);

    $response->assertForbidden();

    expect($target->fresh()->name)->toBe($target->name);

    $guestResponse = $this->put(route('users.update', $target), [
        'name' => 'Updated Name',
        'email' => 'new@example.com',
    ]);

    $guestResponse->assertForbidden();
});

it('prevents removing super admin role from the last super admin', function (): void {
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

    $response->assertSessionHasErrors(['roles' => __('Cannot remove the Super Admin role from the last Super Admin.')]);
});

it('preserves existing roles and inactive status when fields are omitted (BUG-001)', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $role = RoleModel::findOrCreate('editor');

    $target = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'is_active' => false,
    ]);
    $target->assignRole($role);

    $response = $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'New Name',
            'email' => 'original@example.com',
        ]);

    $response->assertRedirectToRoute('users.index');

    $target->refresh();
    expect($target->name)->toBe('New Name')
        ->and($target->is_active)->toBeFalse()
        ->and($target->hasRole('editor'))->toBeTrue();
});

it('ignores role changes when actor lacks users.manage-roles permission (SEC-001)', function (): void {
    $editorRole = RoleModel::findOrCreate('editor');
    RoleModel::findOrCreate(Role::SuperAdmin->value);

    $user = User::factory()->create();
    $user->assignRole($editorRole);
    $user->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $user->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($user)
        ->put(route('users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'roles' => [Role::SuperAdmin->value],
        ]);

    $response->assertRedirectToRoute('users.index');

    $user->refresh();
    expect($user->hasRole(Role::SuperAdmin->value))->toBeFalse()
        ->and($user->hasRole('editor'))->toBeTrue();
});

it('rejects super-admin role changes when actor is not super-admin (SEC-001)', function (): void {
    RoleModel::findOrCreate(Role::SuperAdmin->value);

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $target = User::factory()->create();

    $response = $this->actingAs($actor)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => [Role::SuperAdmin->value],
        ]);

    $response->assertSessionHasErrors(['roles']);

    expect($target->fresh()->hasRole(Role::SuperAdmin->value))->toBeFalse();
});

it('rejects removing super-admin role when actor is not super-admin (SEC-001)', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('editor');

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));

    $target = User::factory()->create();
    $target->assignRole($superAdminRole);

    $response = $this->actingAs($actor)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'roles' => ['editor'],
        ]);

    $response->assertForbidden();

    expect($target->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

/* @chisel-email-verification */
it('resets email verification and sends notification when email changes during admin update', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersUpdate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create([
        'email' => 'old@example.com',
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'Updated Name',
            'email' => 'new@example.com',
        ]);

    $response->assertRedirectToRoute('users.index');

    $target->refresh();
    expect($target->email)->toBe('new@example.com')
        ->and($target->email_verified_at)->toBeNull();

    Notification::assertSentTo($target, VerifyEmail::class);
});
/* @end-chisel-email-verification */
