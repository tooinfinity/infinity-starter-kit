<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as RoleModel;

/* @chisel-roles-permissions */

beforeEach(function (): void {
    RoleModel::findOrCreate(Role::SuperAdmin->value);
});

test('admin setup command creates a new admin user', function (): void {
    $this->artisan('admin:setup')
        ->expectsQuestion('Name', 'Admin User')
        ->expectsQuestion('Email', 'admin@example.com')
        ->expectsQuestion('Password', 'password1234')
        ->assertSuccessful();

    $user = User::query()->where('email', 'admin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Admin User')
        ->and(Hash::check('password1234', $user->password))->toBeTrue()
        ->and($user->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

test('admin setup assigns super admin role to existing user with confirmation', function (): void {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('admin:setup')
        ->expectsQuestion('Name', 'Ignored Name')
        ->expectsQuestion('Email', 'existing@example.com')
        ->expectsConfirmation('Assign the Super Admin role to this existing user?', 'yes')
        ->assertSuccessful();

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

test('admin setup cancels when user declines existing user assignment', function (): void {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('admin:setup')
        ->expectsQuestion('Name', 'Ignored Name')
        ->expectsQuestion('Email', 'existing@example.com')
        ->expectsConfirmation('Assign the Super Admin role to this existing user?', 'no')
        ->assertSuccessful();

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeFalse();
});

/* @end-chisel-roles-permissions */
