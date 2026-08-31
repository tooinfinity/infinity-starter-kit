<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('renders create user page for authorized user', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersCreate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($admin)
        ->get(route('users.create'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('users/create'));
});

it('creates new user with hashed password and roles', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersCreate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    RoleModel::findOrCreate('editor');

    $response = $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'is_active' => true,
            'roles' => ['editor'],
        ]);

    $response->assertRedirectToRoute('users.index');

    $newUser = User::query()->where('email', 'newuser@example.com')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->name)->toBe('New User')
        ->and($newUser->is_active)->toBeTrue()
        ->and(Hash::check('password123', $newUser->password))->toBeTrue()
        ->and($newUser->hasRole('editor'))->toBeTrue();
});

it('validates email uniqueness on user creation', function (): void {
    User::factory()->create(['email' => 'existing@example.com']);
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersCreate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Duplicate Email',
            'email' => 'existing@example.com',
            'password' => 'password123',
        ]);

    $response->assertSessionHasErrors(['email']);
});
