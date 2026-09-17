<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission as PermissionModel;

it('allows authorized user to change another user password', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManagePassword->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create();

    $response = $this->actingAs($admin)
        ->put(route('users.password.update', $target), [
            'password' => 'new-secure-password123',
        ]);

    $response->assertRedirect();

    expect(Hash::check('new-secure-password123', $target->fresh()->password))->toBeTrue();
});

it('forbids password change without permission', function (): void {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $response = $this->actingAs($user)
        ->put(route('users.password.update', $target), [
            'password' => 'new-secure-password123',
        ]);

    $response->assertForbidden();
});
