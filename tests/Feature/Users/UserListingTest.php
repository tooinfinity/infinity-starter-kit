<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;

it('renders user listing page for authorized user', function (): void {
    $admin = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::UsersView->value);
    $admin->givePermissionTo($permission);

    User::factory()->count(3)->create();

    $response = $this->actingAs($admin)
        ->get(route('users.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/index')
            ->has('users')
            ->has('filters'));
});

it('forbids user listing for unauthorized user', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('users.index'));

    $response->assertForbidden();
});

it('filters users by search query', function (): void {
    $admin = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::UsersView->value);
    $admin->givePermissionTo($permission);

    User::factory()->create(['name' => 'Alice Johnson', 'email' => 'alice@example.com']);
    User::factory()->create(['name' => 'Bob Smith', 'email' => 'bob@example.com']);

    $response = $this->actingAs($admin)
        ->get(route('users.index', ['search' => 'Alice']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($data): bool => count($data) === 1 && $data[0]['name'] === 'Alice Johnson'));
});

it('filters users by active status', function (): void {
    $admin = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::UsersView->value);
    $admin->givePermissionTo($permission);

    User::factory()->active()->create(['name' => 'Active User']);
    User::factory()->inactive()->create(['name' => 'Inactive User']);

    $response = $this->actingAs($admin)
        ->get(route('users.index', ['status' => 'inactive']));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('users.data', fn ($data): bool => count($data) === 1 && $data[0]['name'] === 'Inactive User'));
});
