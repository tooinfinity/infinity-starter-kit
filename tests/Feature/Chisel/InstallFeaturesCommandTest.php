<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;
use Laravel\Chisel\Script;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$cleanEnv = function (string $key): void {
    putenv($key);
    unset($GLOBALS['_ENV'][$key], $GLOBALS['_SERVER'][$key]);
};

test('command fails when --answers option does not decode to a JSON object', function (): void {
    $this->artisan('install:features', ['--answers' => 'invalid-json'])
        ->assertFailed();
})->throws(JsonException::class);

test('command fails when --answers option decodes to non-array scalar', function (): void {
    $this->artisan('install:features', ['--answers' => '"just a string"'])
        ->assertFailed();
})->throws(RuntimeException::class, 'The --answers option must decode to a JSON object.');

test('command defers execution when LARAVEL_INSTALLER_DEFER_HOOKS is true and no answers given', function () use ($cleanEnv): void {
    putenv('LARAVEL_INSTALLER_DEFER_HOOKS=true');
    $_ENV['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';
    $_SERVER['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';

    try {
        $this->artisan('install:features')
            ->assertSuccessful();
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_DEFER_HOOKS');
    }
});

test('command rejects invalid dependency combination passed via answers', function () use ($cleanEnv): void {
    putenv('LARAVEL_INSTALLER_NO_NODE=true');
    $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';

    try {
        $answers = json_encode([
            'auth_features' => ['registration'],
            'optional_modules' => ['user-management'],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers]);
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
    }
})->throws(RuntimeException::class, 'The "user-management" module requires the following module(s): authorization.');

test('authorization setup runs and creates permissions and roles when authorization is selected', function (): void {
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->assertSuccessful();

    expect(Permission::query()->count())->toBeGreaterThan(0);

    $superAdmin = Role::findByName(App\Enums\Role::SuperAdmin->value);
    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->permissions)->toHaveCount(count(App\Enums\Permission::cases()));
});

test('authorization setup and admin setup are skipped when authorization is disabled', function (): void {
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => [],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->assertSuccessful();

    expect(Permission::query()->count())->toBe(0)
        ->and(Role::query()->where('name', App\Enums\Role::SuperAdmin->value)->exists())->toBeFalse()
        ->and(User::query()->count())->toBe(0);
});

test('admin setup runs and creates administrator when credentials are provided in answers', function (): void {
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
        'admin' => [
            'name' => 'Root Admin',
            'email' => 'root@example.com',
            'password' => 'secret1234',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->doesntExpectOutputToContain('secret1234')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'root@example.com')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Root Admin')
        ->and(Hash::check('secret1234', $admin->password))->toBeTrue()
        ->and($admin->hasRole(App\Enums\Role::SuperAdmin->value))->toBeTrue();
});

test('admin setup runs and creates administrator when credentials are provided via options', function (): void {
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', [
        '--answers' => $answers,
        '--admin-name' => 'Option Admin',
        '--admin-email' => 'option@example.com',
        '--admin-password' => 'optionpassword123',
    ])
        ->doesntExpectOutputToContain('optionpassword123')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'option@example.com')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Option Admin')
        ->and(Hash::check('optionpassword123', $admin->password))->toBeTrue()
        ->and($admin->hasRole(App\Enums\Role::SuperAdmin->value))->toBeTrue();
});

test('admin setup safely handles existing user in non-interactive installation without changing password', function (): void {
    $existing = User::factory()->create([
        'email' => 'existing-admin@example.com',
        'password' => 'original-pass-1234',
    ]);
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
        'admin' => [
            'email' => 'existing-admin@example.com',
            'password' => 'new-ignored-pass-9999',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->assertSuccessful();

    $fresh = $existing->fresh();
    expect($fresh->hasRole(App\Enums\Role::SuperAdmin->value))->toBeTrue()
        ->and(Hash::check('original-pass-1234', $fresh->password))->toBeTrue();
});

test('admin setup fails installation if invalid credentials provided and never leaks password', function (): void {
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
        'admin' => [
            'name' => 'Bad Admin',
            'email' => 'bad@example.com',
            'password' => 'short7!',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->expectsOutputToContain('Administrator setup failed.')
        ->doesntExpectOutputToContain('short7!')
        ->assertFailed();
});

test('admin setup fails installation when password is provided without email', function (): void {
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
        'admin' => [
            'name' => 'No Email Admin',
            'password' => 'valid-password-1234',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->expectsOutputToContain('Administrator setup failed.')
        ->doesntExpectOutputToContain('valid-password-1234')
        ->assertFailed();
});

test('admin setup does not create duplicate users on repeated execution', function (): void {
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
        'admin' => [
            'name' => 'Admin Twice',
            'email' => 'twice@example.com',
            'password' => 'password1234',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->assertSuccessful();

    bindMockChiselScript();
    $this->artisan('install:features', ['--answers' => $answers])
        ->assertSuccessful();

    expect(User::query()->where('email', 'twice@example.com')->count())->toBe(1)
        ->and(User::query()->where('email', 'twice@example.com')->first()->hasRole(App\Enums\Role::SuperAdmin->value))->toBeTrue();
});

test('admin setup assigns role to existing user already with Super Admin without error', function (): void {
    $existing = User::factory()->create(['email' => 'already-super@example.com']);
    $this->artisan('authorization:setup')->assertSuccessful();
    $existing->assignRole(App\Enums\Role::SuperAdmin->value);

    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
        'admin' => [
            'email' => 'already-super@example.com',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->assertSuccessful();

    expect($existing->fresh()->hasRole(App\Enums\Role::SuperAdmin->value))->toBeTrue();
});

test('non-interactive mode with authorization enabled skips admin when no credentials provided', function (): void {
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});

test('command does not defer when LARAVEL_INSTALLER_DEFER_HOOKS is true but answers are provided', function (): void {
    $cleanEnv = function (string $key): void {
        putenv($key);
        unset($GLOBALS['_ENV'][$key], $GLOBALS['_SERVER'][$key]);
    };

    putenv('LARAVEL_INSTALLER_DEFER_HOOKS=true');
    $_ENV['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';
    $_SERVER['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';

    bindMockChiselScript();

    try {
        $answers = json_encode([
            'auth_features' => ['registration'],
            'optional_modules' => ['authorization'],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        expect(Permission::query()->count())->toBeGreaterThan(0);
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_DEFER_HOOKS');
    }
});

test('normal Node mode executes frontend install, build, and lint commands', function () use ($cleanEnv): void {
    $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => [],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->assertSuccessful();

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['bun', 'install']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['bun', 'run', 'build']);
    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['bun', 'run', 'lint']);
});

test('LARAVEL_INSTALLER_NO_NODE mode skips all Node commands', function () use ($cleanEnv): void {
    putenv('LARAVEL_INSTALLER_NO_NODE=true');
    $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';
    $_SERVER['LARAVEL_INSTALLER_NO_NODE'] = 'true';

    bindMockChiselScript();

    try {
        $answers = json_encode([
            'auth_features' => ['registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        Process::assertNotRan(fn (PendingProcess $process): bool => is_array($process->command) && in_array($process->command[0] ?? '', ['bun', 'npm', 'pnpm', 'yarn'], true));
    } finally {
        $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
    }
});

test('failed authorization setup returns non-zero exit status and does not claim success', function (): void {
    bindMockChiselScript();

    Artisan::command('authorization:setup', fn (): int => 1);

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->expectsOutputToContain('Authorization setup failed.')
        ->assertFailed();
});

test('failed Wayfinder generation returns non-zero exit status and outputs error', function (): void {
    bindMockChiselScript([
        '*wayfinder:generate*' => Process::result(errorOutput: 'Wayfinder route generation failed.', exitCode: 1),
    ]);

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => [],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->expectsOutputToContain('Wayfinder route generation failed.')
        ->assertFailed();
});

test('failed frontend installation returns non-zero exit status and outputs error', function () use ($cleanEnv): void {
    $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
    bindMockChiselScript([
        '*bun*install*' => Process::result(errorOutput: 'bun install failed: network error.', exitCode: 1),
    ]);

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => [],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->expectsOutputToContain('bun install failed: network error.')
        ->assertFailed();
});

test('failed frontend build returns non-zero exit status and outputs error', function () use ($cleanEnv): void {
    $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
    bindMockChiselScript([
        '*bun*build*' => Process::result(errorOutput: 'Vite build failed: syntax error.', exitCode: 1),
    ]);

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => [],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->expectsOutputToContain('Vite build failed: syntax error.')
        ->assertFailed();
});

test('failed frontend lint returns non-zero exit status and outputs error', function () use ($cleanEnv): void {
    $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
    bindMockChiselScript([
        '*bun*lint*' => Process::result(errorOutput: 'Linting failed with 1 error.', exitCode: 1),
    ]);

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => [],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->expectsOutputToContain('Linting failed with 1 error.')
        ->assertFailed();
});

/**
 * @param  array<string, mixed>  $processFakes
 */
function bindMockChiselScript(array $processFakes = []): Script
{
    Process::fake($processFakes === [] ? ['*' => Process::result()] : $processFakes + ['*' => Process::result()]);

    $script = Chisel::script(base_path())
        ->questions([
            Question::multiselect(
                name: 'auth_features',
                label: 'Auth features',
                options: ['registration' => 'Registration'],
                default: ['registration'],
            ),
            Question::multiselect(
                name: 'optional_modules',
                label: 'Optional modules',
                options: [
                    'authorization' => 'Authorization',
                    'settings' => 'Settings',
                    'user-management' => 'User Management',
                    'localization' => 'Localization',
                    'notifications' => 'Notifications',
                    'audit-trails' => 'Audit Trails',
                    'reporting' => 'Reporting',
                ],
                default: ['authorization'],
            ),
        ]);

    app()->instance(Script::class, $script);

    return $script;
}
