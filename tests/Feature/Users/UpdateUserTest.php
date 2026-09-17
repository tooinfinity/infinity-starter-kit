<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\User;
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
