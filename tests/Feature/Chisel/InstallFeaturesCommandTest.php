<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;
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
    ])->assertSuccessful();

    $admin = User::query()->where('email', 'option@example.com')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe('Option Admin')
        ->and(Hash::check('optionpassword123', $admin->password))->toBeTrue()
        ->and($admin->hasRole(App\Enums\Role::SuperAdmin->value))->toBeTrue();
});

test('admin setup safely handles existing user in non-interactive installation', function (): void {
    $existing = User::factory()->create(['email' => 'existing-admin@example.com']);
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
        'admin' => [
            'email' => 'existing-admin@example.com',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->assertSuccessful();

    expect($existing->fresh()->hasRole(App\Enums\Role::SuperAdmin->value))->toBeTrue();
});

test('admin setup fails installation if invalid credentials provided', function (): void {
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['registration'],
        'optional_modules' => ['authorization'],
        'admin' => [
            'name' => 'Bad Admin',
            'email' => 'bad@example.com',
            'password' => 'short',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->assertFailed();
});

function bindMockChiselScript(): Script
{
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
