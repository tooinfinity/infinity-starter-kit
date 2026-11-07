<?php

declare(strict_types=1);

use App\Console\Commands\CreateAdminCommand;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function (): void {
    RoleModel::findOrCreate(Role::SuperAdmin->value);
    CreateAdminCommand::setInputStream(null);
});

afterEach(function (): void {
    CreateAdminCommand::setInputStream(null);
});

test('create admin command creates a new admin user interactively', function (): void {
    $this->artisan('authorization:create-admin')
        ->expectsQuestion('Email', 'admin@example.com')
        ->expectsQuestion('Name', 'Admin User')
        ->expectsQuestion('Password', 'password1234')
        ->expectsQuestion('Confirm password', 'password1234')
        ->assertSuccessful();

    $user = User::query()->where('email', 'admin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Admin User')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('password1234', $user->password))->toBeTrue()
        ->and($user->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

test('create admin command creates a new admin user non-interactively via stdin', function (): void {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "secure-pass-1234\n");
    rewind($stream);
    CreateAdminCommand::setInputStream($stream);

    $this->artisan('authorization:create-admin', [
        '--email' => 'admin-stdin@example.com',
        '--name' => 'Stdin Admin',
        '--no-interaction' => true,
    ])->assertSuccessful();

    fclose($stream);

    $user = User::query()->where('email', 'admin-stdin@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Stdin Admin')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(Hash::check('secure-pass-1234', $user->password))->toBeTrue()
        ->and($user->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

test('create admin command defaults to Administrator when name is omitted non-interactively', function (): void {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "secure-pass-1234\n");
    rewind($stream);
    CreateAdminCommand::setInputStream($stream);

    $this->artisan('authorization:create-admin', [
        '--email' => 'admin-default-name@example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    fclose($stream);

    $user = User::query()->where('email', 'admin-default-name@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Administrator')
        ->and($user->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

test('create admin command promotes an existing user with confirmation interactively', function (): void {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('authorization:create-admin')
        ->expectsQuestion('Email', 'existing@example.com')
        ->expectsConfirmation('Assign the Super Admin role to this existing user?', 'yes')
        ->assertSuccessful();

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

test('create admin command cancels when user declines promoting existing user', function (): void {
    $user = User::factory()->create(['email' => 'existing@example.com']);

    $this->artisan('authorization:create-admin')
        ->expectsQuestion('Email', 'existing@example.com')
        ->expectsConfirmation('Assign the Super Admin role to this existing user?', 'no')
        ->assertSuccessful();

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeFalse();
});

test('create admin command promotes an existing user directly when email is passed as option', function (): void {
    $user = User::factory()->create(['email' => 'existing-opt@example.com']);

    $this->artisan('authorization:create-admin', [
        '--email' => 'existing-opt@example.com',
        '--no-interaction' => true,
    ])->assertSuccessful();

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

test('create admin command informs if user already has Super Admin role', function (): void {
    $user = User::factory()->create(['email' => 'already-admin@example.com']);
    $user->assignRole(Role::SuperAdmin->value);

    $this->artisan('authorization:create-admin', [
        '--email' => 'already-admin@example.com',
        '--force' => true,
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('already has the Super Admin role')
        ->assertSuccessful();
});

test('create admin command refuses by default when a Super Admin already exists', function (): void {
    $admin = User::factory()->create(['email' => 'current-admin@example.com']);
    $admin->assignRole(Role::SuperAdmin->value);

    $this->artisan('authorization:create-admin', [
        '--email' => 'another-admin@example.com',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('A Super Admin already exists. Use --force to proceed.')
        ->assertFailed();

    expect(User::query()->where('email', 'another-admin@example.com')->first())->toBeNull();
});

test('create admin command creates another admin when force option is supplied', function (): void {
    $admin = User::factory()->create(['email' => 'current-admin@example.com']);
    $admin->assignRole(Role::SuperAdmin->value);

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "secure-pass-1234\n");
    rewind($stream);
    CreateAdminCommand::setInputStream($stream);

    $this->artisan('authorization:create-admin', [
        '--email' => 'second-admin@example.com',
        '--force' => true,
        '--no-interaction' => true,
    ])->assertSuccessful();

    fclose($stream);

    expect(User::query()->where('email', 'second-admin@example.com')->first())->not->toBeNull()
        ->and(User::query()->where('email', 'second-admin@example.com')->first()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

test('create admin command rejects weak passwords that Password::defaults rejects', function (): void {
    Password::defaults(fn () => Password::min(12));

    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "short\n");
    rewind($stream);
    CreateAdminCommand::setInputStream($stream);

    try {
        $this->artisan('authorization:create-admin', [
            '--email' => 'weak-pass@example.com',
            '--no-interaction' => true,
        ])
            ->expectsOutputToContain('The password field must be at least 12 characters.')
            ->assertFailed();
    } finally {
        fclose($stream);
        Password::defaults(fn () => Password::min(8));
    }

    expect(User::query()->where('email', 'weak-pass@example.com')->first())->toBeNull();
});

test('create admin command fails in non-interactive mode when no email is provided', function (): void {
    $this->artisan('authorization:create-admin', ['--no-interaction' => true])
        ->expectsOutputToContain('A valid email address is required in non-interactive mode.')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

test('create admin command fails in non-interactive mode when stdin is empty', function (): void {
    $stream = fopen('php://memory', 'r+');
    rewind($stream);
    CreateAdminCommand::setInputStream($stream);

    $this->artisan('authorization:create-admin', [
        '--email' => 'empty-stdin@example.com',
        '--no-interaction' => true,
    ])
        ->expectsOutputToContain('A valid password is required via STDIN.')
        ->assertFailed();

    fclose($stream);

    expect(User::query()->where('email', 'empty-stdin@example.com')->first())->toBeNull();
});

test('create admin command never leaks administrator password in console output', function (): void {
    $secret = 'SecretSuperPassword99!';
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "{$secret}\n");
    rewind($stream);
    CreateAdminCommand::setInputStream($stream);

    $this->artisan('authorization:create-admin', [
        '--email' => 'secret-test@example.com',
        '--no-interaction' => true,
    ])
        ->doesntExpectOutputToContain($secret)
        ->assertSuccessful();

    fclose($stream);
});

test('first login of the created admin via authorization:create-admin reaches dashboard', function (): void {
    $password = 'dashboard-pass-1234';
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, "{$password}\n");
    rewind($stream);
    CreateAdminCommand::setInputStream($stream);

    $this->artisan('authorization:create-admin', [
        '--email' => 'dash-admin@example.com',
        '--name' => 'Dashboard SuperAdmin',
        '--no-interaction' => true,
    ])->assertSuccessful();

    fclose($stream);

    $response = $this->post(route('login.store'), [
        'email' => 'dash-admin@example.com',
        'password' => $password,
    ]);

    $response->assertRedirectToRoute('dashboard');

    $this->assertAuthenticated();
    $this->get(route('dashboard'))->assertOk();
});
