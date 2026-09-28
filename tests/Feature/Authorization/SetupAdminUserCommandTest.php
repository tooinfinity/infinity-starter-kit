<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role as RoleModel;

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

test('admin setup skips creation in non-interactive mode when no email is provided', function (): void {
    $this->artisan('admin:setup', ['--no-interaction' => true])
        ->expectsOutputToContain('No administrator credentials provided in non-interactive mode; skipping administrator creation.')
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});

test('admin setup creates administrator from CLI options with default name when name is omitted', function (): void {
    $this->artisan('admin:setup', [
        '--email' => 'admin-options@example.com',
        '--password' => 'secure-pass-1234',
        '--no-interaction' => true,
    ])->assertSuccessful();

    $user = User::query()->where('email', 'admin-options@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Administrator')
        ->and(Hash::check('secure-pass-1234', $user->password))->toBeTrue()
        ->and($user->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

test('admin setup fails when provided password is under 8 characters', function (): void {
    $this->artisan('admin:setup', [
        '--name' => 'Short Pass Admin',
        '--email' => 'short@example.com',
        '--password' => 'short',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('The administrator password must be at least 8 characters.')
        ->assertFailed();

    expect(User::query()->where('email', 'short@example.com')->first())->toBeNull();
});

test('admin setup fails in non-interactive mode when email is provided without password', function (): void {
    $this->artisan('admin:setup', [
        '--name' => 'No Pass Admin',
        '--email' => 'nopass@example.com',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('A password of at least 8 characters is required to create an administrator user in non-interactive mode.')
        ->assertFailed();

    expect(User::query()->where('email', 'nopass@example.com')->first())->toBeNull();
});

test('admin setup assigns super admin role to existing user directly when email is provided via option', function (): void {
    $user = User::factory()->create(['email' => 'existing-option@example.com']);

    $this->artisan('admin:setup', [
        '--email' => 'existing-option@example.com',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('A user with email [existing-option@example.com] already exists.')
        ->assertSuccessful();

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

test('admin setup does not create duplicate users on repeated execution', function (): void {
    $this->artisan('admin:setup', [
        '--name' => 'Dupe Admin',
        '--email' => 'dupe-admin@example.com',
        '--password' => 'secure-pass-1234',
        '--no-interaction' => true,
    ])->assertSuccessful();

    // Run again with same email — should assign role to existing user, not create a duplicate
    $this->artisan('admin:setup', [
        '--email' => 'dupe-admin@example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect(User::query()->where('email', 'dupe-admin@example.com')->count())->toBe(1)
        ->and(User::query()->where('email', 'dupe-admin@example.com')->first()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

test('admin setup is safe when existing user already has Super Admin role', function (): void {
    $user = User::factory()->create(['email' => 'already-admin@example.com']);
    $user->assignRole(Role::SuperAdmin->value);

    $this->artisan('admin:setup', [
        '--email' => 'already-admin@example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and($user->fresh()->roles()->where('name', Role::SuperAdmin->value)->count())->toBe(1);
});
