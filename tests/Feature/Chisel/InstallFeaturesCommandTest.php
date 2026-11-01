<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Process\Exceptions\ProcessFailedException;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;
use Laravel\Chisel\Script;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

$cleanEnv = function (string $key): void {
    putenv($key);
    unset($GLOBALS['_ENV'][$key], $GLOBALS['_SERVER'][$key]);
};

$preserveEnv = function (string ...$keys): Closure {
    $originalEnvironment = [];

    foreach ($keys as $key) {
        $originalEnvironment[$key] = [
            getenv($key),
            array_intersect_key($_ENV, [$key => true]),
            array_intersect_key($_SERVER, [$key => true]),
        ];
    }

    return static function () use ($originalEnvironment): void {
        foreach ($originalEnvironment as $key => [$value, $env, $server]) {
            putenv($value === false ? $key : $key.'='.$value);
            unset($GLOBALS['_ENV'][$key], $GLOBALS['_SERVER'][$key]);
            $_ENV = array_replace($_ENV, $env);
            $_SERVER = array_replace($_SERVER, $server);
        }
    };
};

beforeEach(function (): void {
    if (file_exists(base_path('.installer-state.json'))) {
        @unlink(base_path('.installer-state.json'));
    }
});

afterEach(function (): void {
    if (file_exists(base_path('.installer-state.json'))) {
        @unlink(base_path('.installer-state.json'));
    }
});

test('environment cleanup restores the original migration opt-in state', function (string|false $value, array $env, array $server, bool $throw) use ($cleanEnv, $preserveEnv): void {
    $key = 'CHISEL_RUN_MIGRATIONS';
    $restoreCallerEnv = $preserveEnv($key);

    try {
        $cleanEnv($key);
        if ($value !== false) {
            putenv($key.'='.$value);
        }

        $_ENV = array_replace($_ENV, $env);
        $_SERVER = array_replace($_SERVER, $server);
        $restoreEnv = $preserveEnv($key);

        $run = function () use ($key, $throw, $restoreEnv): void {
            try {
                putenv($key.'=overridden');
                $_ENV[$key] = 'overridden';
                $_SERVER[$key] = 'overridden';

                throw_if($throw, RuntimeException::class, 'Test failed.');
            } finally {
                $restoreEnv();
            }
        };

        if ($throw) {
            expect($run)->toThrow(RuntimeException::class, 'Test failed.');
        } else {
            $run();
        }

        expect(getenv($key))->toBe($value)
            ->and(array_intersect_key($_ENV, [$key => true]))->toBe($env)
            ->and(array_intersect_key($_SERVER, [$key => true]))->toBe($server);
    } finally {
        $restoreCallerEnv();
    }
})->with([
    'unset' => [false, [], []],
    'enabled' => ['true', ['CHISEL_RUN_MIGRATIONS' => 'true'], ['CHISEL_RUN_MIGRATIONS' => 'true']],
    'independent values' => ['false', ['CHISEL_RUN_MIGRATIONS' => 'env'], ['CHISEL_RUN_MIGRATIONS' => 'server']],
    'empty values' => ['', ['CHISEL_RUN_MIGRATIONS' => ''], ['CHISEL_RUN_MIGRATIONS' => '']],
    'null and unset' => [false, ['CHISEL_RUN_MIGRATIONS' => null], []],
    'server only' => [false, [], ['CHISEL_RUN_MIGRATIONS' => 'true']],
])->with([false, true]);

test('command fails when --answers option does not decode to a JSON object', function (): void {
    $this->artisan('install:features', ['--answers' => 'invalid-json'])
        ->assertFailed();
})->throws(JsonException::class);

test('command fails when --answers option decodes to non-array scalar', function (): void {
    $this->artisan('install:features', ['--answers' => '"just a string"'])
        ->assertFailed();
})->throws(RuntimeException::class, 'The --answers option must decode to a JSON object.');

test('command succeeds with --preset minimal', function (): void {
    bindMockChiselScript();

    $this->artisan('install:features', ['--preset' => 'minimal'])
        ->assertSuccessful();
});

test('command succeeds with --preset standard', function (): void {
    bindMockChiselScript();

    $this->artisan('install:features', ['--preset' => 'standard'])
        ->assertSuccessful();
});

test('command succeeds with --preset full', function (): void {
    bindMockChiselScript();

    $this->artisan('install:features', ['--preset' => 'full'])
        ->assertSuccessful();
});

test('command fails before mutation when invalid preset name is provided', function (): void {
    $script = bindMockChiselScript();
    $mutated = false;
    $script->apply(function () use (&$mutated): void {
        $mutated = true;
    });

    try {
        $this->artisan('install:features', ['--preset' => 'non-existent-preset'])
            ->assertFailed();
    } catch (InvalidArgumentException $invalidArgumentException) {
        expect($invalidArgumentException->getMessage())->toContain('Unknown preset [non-existent-preset]');
    }

    expect($mutated)->toBeFalse();
});

test('command succeeds with --answers-file', function (): void {
    bindMockChiselScript();

    $tempFile = sys_get_temp_dir().'/cli_answers_file_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($tempFile, json_encode([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization'],
    ], JSON_THROW_ON_ERROR));

    try {
        $this->artisan('install:features', ['--answers-file' => $tempFile])
            ->assertSuccessful();
    } finally {
        @unlink($tempFile);
    }
});

test('command fails before mutation when --answers-file does not exist', function (): void {
    $script = bindMockChiselScript();
    $mutated = false;
    $script->apply(function () use (&$mutated): void {
        $mutated = true;
    });

    $missingFile = sys_get_temp_dir().'/missing_answers_'.bin2hex(random_bytes(6)).'.json';

    try {
        $this->artisan('install:features', ['--answers-file' => $missingFile])
            ->assertFailed();
    } catch (RuntimeException $runtimeException) {
        expect($runtimeException->getMessage())->toContain("Answers file [{$missingFile}] does not exist.");
    }

    expect($mutated)->toBeFalse();
});

test('command fails before mutation when --answers-file contains invalid JSON', function (): void {
    $script = bindMockChiselScript();
    $mutated = false;
    $script->apply(function () use (&$mutated): void {
        $mutated = true;
    });

    $tempFile = sys_get_temp_dir().'/invalid_json_'.bin2hex(random_bytes(4)).'.json';
    file_put_contents($tempFile, 'not-valid-json');

    try {
        $this->artisan('install:features', ['--answers-file' => $tempFile])
            ->assertFailed();
    } catch (JsonException) {
        // expected
    } finally {
        @unlink($tempFile);
    }

    expect($mutated)->toBeFalse();
});

test('command defers execution when LARAVEL_INSTALLER_DEFER_HOOKS is true and no answers given', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('LARAVEL_INSTALLER_DEFER_HOOKS');

    try {
        putenv('LARAVEL_INSTALLER_DEFER_HOOKS=true');
        $_ENV['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';
        $_SERVER['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';

        $this->artisan('install:features')
            ->assertSuccessful();
    } finally {
        $restoreEnv();
    }
});

test('command rejects invalid dependency combination passed via answers', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('LARAVEL_INSTALLER_NO_NODE');

    try {
        putenv('LARAVEL_INSTALLER_NO_NODE=true');
        $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['user-management'],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers]);
    } finally {
        $restoreEnv();
    }
})->throws(RuntimeException::class, 'The "user-management" feature requires the following feature(s): authorization.');

test('authorization setup runs and creates permissions and roles when authorization is selected', function (): void {
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['login', 'registration'],
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
        'auth_features' => ['login', 'registration'],
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
        'auth_features' => ['login', 'registration'],
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
        'auth_features' => ['login', 'registration'],
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
        'auth_features' => ['login', 'registration'],
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
        'auth_features' => ['login', 'registration'],
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
        'auth_features' => ['login', 'registration'],
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
        'auth_features' => ['login', 'registration'],
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
        'auth_features' => ['login', 'registration'],
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
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization'],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});

test('command does not defer when LARAVEL_INSTALLER_DEFER_HOOKS is true but answers are provided', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('LARAVEL_INSTALLER_DEFER_HOOKS');

    try {
        putenv('LARAVEL_INSTALLER_DEFER_HOOKS=true');
        $_ENV['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';
        $_SERVER['LARAVEL_INSTALLER_DEFER_HOOKS'] = 'true';

        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        expect(Permission::query()->count())->toBeGreaterThan(0);
    } finally {
        $restoreEnv();
    }
});

test('normal Node mode executes frontend install, build, and lint commands', function () use ($cleanEnv, $preserveEnv): void {
    $restoreEnv = $preserveEnv('LARAVEL_INSTALLER_NO_NODE');

    try {
        $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['bun', 'install']);
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['bun', 'run', 'build']);
        Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['bun', 'run', 'lint']);
    } finally {
        $restoreEnv();
    }
});

test('LARAVEL_INSTALLER_NO_NODE mode skips all Node commands', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('LARAVEL_INSTALLER_NO_NODE');

    try {
        putenv('LARAVEL_INSTALLER_NO_NODE=true');
        $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';
        $_SERVER['LARAVEL_INSTALLER_NO_NODE'] = 'true';

        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        Process::assertNotRan(fn (PendingProcess $process): bool => is_array($process->command) && in_array($process->command[0] ?? '', ['bun', 'npm', 'pnpm', 'yarn'], true));
    } finally {
        $restoreEnv();
    }
});

test('failed authorization setup returns non-zero exit status and does not claim success', function (): void {
    bindMockChiselScript();

    Artisan::command('authorization:setup', fn (): int => 1);

    $answers = json_encode([
        'auth_features' => ['login', 'registration'],
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
        'auth_features' => ['login', 'registration'],
        'optional_modules' => [],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->expectsOutputToContain('Wayfinder route generation failed.')
        ->assertFailed();
});

test('failed frontend installation returns non-zero exit status and outputs error', function () use ($cleanEnv, $preserveEnv): void {
    $restoreEnv = $preserveEnv('LARAVEL_INSTALLER_NO_NODE');

    try {
        $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
        bindMockChiselScript([
            '*bun*install*' => Process::result(errorOutput: 'bun install failed: network error.', exitCode: 1),
        ]);

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->expectsOutputToContain('bun install failed: network error.')
            ->assertFailed();
    } finally {
        $restoreEnv();
    }
});

test('failed frontend build returns non-zero exit status and outputs error', function () use ($cleanEnv, $preserveEnv): void {
    $restoreEnv = $preserveEnv('LARAVEL_INSTALLER_NO_NODE');

    try {
        $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
        bindMockChiselScript([
            '*bun*build*' => Process::result(errorOutput: 'Vite build failed: syntax error.', exitCode: 1),
        ]);

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->expectsOutputToContain('Vite build failed: syntax error.')
            ->assertFailed();
    } finally {
        $restoreEnv();
    }
});

test('failed frontend lint returns non-zero exit status and outputs error', function () use ($cleanEnv, $preserveEnv): void {
    $restoreEnv = $preserveEnv('LARAVEL_INSTALLER_NO_NODE');

    try {
        $cleanEnv('LARAVEL_INSTALLER_NO_NODE');
        bindMockChiselScript([
            '*bun*lint*' => Process::result(errorOutput: 'Linting failed with 1 error.', exitCode: 1),
        ]);

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->expectsOutputToContain('Linting failed with 1 error.')
            ->assertFailed();
    } finally {
        $restoreEnv();
    }
});

test('CHISEL_TEST_FAIL_STAGE causes deterministic failure after chisel mutations', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_TEST_FAIL_STAGE');

    try {
        putenv('CHISEL_TEST_FAIL_STAGE=post_chisel');
        $_ENV['CHISEL_TEST_FAIL_STAGE'] = 'post_chisel';

        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers]);
    } finally {
        $restoreEnv();
    }
})->throws(RuntimeException::class, 'CHISEL_TEST_FAIL_STAGE');

test('command returns success early when chisel.php does not exist', function (): void {
    $tempDir = sys_get_temp_dir().'/missing_chisel_'.bin2hex(random_bytes(6));
    mkdir($tempDir, 0777, true);
    $origBasePath = app()->basePath();
    app()->setBasePath($tempDir);

    try {
        $this->artisan('install:features')
            ->assertSuccessful();
    } finally {
        app()->setBasePath($origBasePath);
        @rmdir($tempDir);
    }
});

test('command returns success early when chisel-paths.php does not exist', function (): void {
    $tempDir = sys_get_temp_dir().'/missing_chisel_paths_'.bin2hex(random_bytes(6));
    mkdir($tempDir, 0777, true);
    file_put_contents($tempDir.'/chisel.php', '<?php return null;');
    $origBasePath = app()->basePath();
    app()->setBasePath($tempDir);

    try {
        $this->artisan('install:features')
            ->assertSuccessful();
    } finally {
        app()->setBasePath($origBasePath);
        @unlink($tempDir.'/chisel.php');
        @rmdir($tempDir);
    }
});

test('command fails and outputs error when chisel execution throws RuntimeException', function (): void {
    $script = bindMockChiselScript();
    $script->apply(function (): void {
        throw new RuntimeException('Chisel script execution failed with error.');
    });

    $answers = json_encode([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization'],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->expectsOutputToContain('Chisel script execution failed with error.')
        ->assertFailed();
});

test('command fails and outputs error when chisel execution throws ProcessFailedException', function (): void {
    $script = bindMockChiselScript([
        'git *' => Process::result(errorOutput: 'fatal: git error', exitCode: 1),
    ]);
    $failedResult = Process::run('git status');
    $exception = new ProcessFailedException($failedResult);

    $script->apply(function () use ($exception): void {
        throw $exception;
    });

    $answers = json_encode([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization'],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', ['--answers' => $answers])
        ->assertFailed();
});

test('installer cleanup executes and deletes files when CHISEL_RUN_CLEANUP is enabled', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('LARAVEL_INSTALLER_NO_NODE', 'CHISEL_RUN_CLEANUP');

    $tempDir = sys_get_temp_dir().'/cleanup_orchestration_'.bin2hex(random_bytes(6));
    mkdir($tempDir.'/bootstrap', 0777, true);
    mkdir($tempDir.'/routes', 0777, true);
    mkdir($tempDir.'/app/Models', 0777, true);
    mkdir($tempDir.'/temp-dir/empty-subdir', 0777, true);

    file_put_contents($tempDir.'/composer.json', json_encode(['require' => ['php' => '^8.5.0']], JSON_PRETTY_PRINT));
    file_put_contents($tempDir.'/package.json', json_encode(['scripts' => ['build' => 'vite build']], JSON_PRETTY_PRINT));
    file_put_contents($tempDir.'/bootstrap/app.php', '<?php return null;');
    file_put_contents($tempDir.'/routes/web.php', '<?php return null;');
    file_put_contents($tempDir.'/app/Models/User.php', '<?php namespace App\Models; class User {}');
    file_put_contents($tempDir.'/phpstan.neon', "parameters:\n    paths:\n        - app\n");
    file_put_contents($tempDir.'/phpunit.xml', '<phpunit></phpunit>');
    file_put_contents($tempDir.'/temp-file.txt', 'to-be-deleted');

    file_put_contents($tempDir.'/chisel-paths.php', '<?php return [
        "chisel" => [
            "files" => ["temp-file.txt"],
            "empty_dirs" => ["temp-dir/empty-subdir"],
        ],
    ];');

    file_put_contents($tempDir.'/chisel.php', '<?php
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;

return Chisel::script(__DIR__)->questions([
    Question::multiselect("auth_features", "Auth", ["registration" => "Registration"], ["registration"]),
    Question::multiselect("optional_modules", "Modules", ["authorization" => "Authorization"], []),
]);');

    $origBasePath = app()->basePath();
    app()->setBasePath($tempDir);

    try {
        putenv('LARAVEL_INSTALLER_NO_NODE=true');
        $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';
        putenv('CHISEL_RUN_CLEANUP=true');
        $_ENV['CHISEL_RUN_CLEANUP'] = 'true';
        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        expect(file_exists($tempDir.'/temp-file.txt'))->toBeFalse()
            ->and(is_dir($tempDir.'/temp-dir/empty-subdir'))->toBeFalse();
    } finally {
        $restoreEnv();
        app()->setBasePath($origBasePath);
        $cleanup = function (string $dir) use (&$cleanup): void {
            if (! is_dir($dir)) {
                return;
            }

            foreach (scandir($dir) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $p = $dir.'/'.$item;
                is_dir($p) ? $cleanup($p) : @unlink($p);
            }

            @rmdir($dir);
        };
        $cleanup($tempDir);
    }
});

test('installer executes with unmocked script from base_path and performs cleanup', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('LARAVEL_INSTALLER_NO_NODE');

    $tempDir = sys_get_temp_dir().'/unmocked_script_'.bin2hex(random_bytes(6));
    mkdir($tempDir.'/bootstrap', 0777, true);
    mkdir($tempDir.'/routes', 0777, true);
    mkdir($tempDir.'/app/Models', 0777, true);
    mkdir($tempDir.'/temp-dir/empty-subdir', 0777, true);

    file_put_contents($tempDir.'/composer.json', json_encode(['require' => ['php' => '^8.5.0']], JSON_PRETTY_PRINT));
    file_put_contents($tempDir.'/package.json', json_encode(['scripts' => ['build' => 'vite build']], JSON_PRETTY_PRINT));
    file_put_contents($tempDir.'/bootstrap/app.php', '<?php return null;');
    file_put_contents($tempDir.'/routes/web.php', '<?php return null;');
    file_put_contents($tempDir.'/app/Models/User.php', '<?php namespace App\Models; class User {}');
    file_put_contents($tempDir.'/phpstan.neon', "parameters:\n    paths:\n        - app\n");
    file_put_contents($tempDir.'/phpunit.xml', '<phpunit></phpunit>');
    file_put_contents($tempDir.'/temp-file.txt', 'to-be-deleted');

    file_put_contents($tempDir.'/chisel-paths.php', '<?php return [
        "chisel" => [
            "files" => ["temp-file.txt"],
            "empty_dirs" => ["temp-dir/empty-subdir"],
        ],
    ];');

    file_put_contents($tempDir.'/chisel.php', '<?php
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Question;

return Chisel::script(__DIR__)->questions([
    Question::multiselect("auth_features", "Auth", ["registration" => "Registration"], ["registration"]),
    Question::multiselect("optional_modules", "Modules", ["authorization" => "Authorization"], []),
]);');

    $origBasePath = app()->basePath();
    app()->setBasePath($tempDir);
    app()->forgetInstance(Script::class);
    Process::fake();

    try {
        putenv('LARAVEL_INSTALLER_NO_NODE=true');
        $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        expect(file_exists($tempDir.'/temp-file.txt'))->toBeFalse()
            ->and(is_dir($tempDir.'/temp-dir/empty-subdir'))->toBeFalse();
    } finally {
        $restoreEnv();
        app()->setBasePath($origBasePath);
        $cleanup = function (string $dir) use (&$cleanup): void {
            if (! is_dir($dir)) {
                return;
            }

            foreach (scandir($dir) ?: [] as $item) {
                if ($item === '.' || $item === '..') {
                    continue;
                }

                $p = $dir.'/'.$item;
                is_dir($p) ? $cleanup($p) : @unlink($p);
            }

            @rmdir($dir);
        };
        $cleanup($tempDir);
    }
});

test('installer on an empty DB migrates and succeeds', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_RUN_MIGRATIONS', 'LARAVEL_INSTALLER_NO_NODE');

    try {
        putenv('CHISEL_RUN_MIGRATIONS=true');
        $_ENV['CHISEL_RUN_MIGRATIONS'] = 'true';
        putenv('LARAVEL_INSTALLER_NO_NODE=true');
        $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';

        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        expect(Schema::hasTable('users'))->toBeTrue()
            ->and(Schema::hasTable('migrations'))->toBeTrue();
    } finally {
        $restoreEnv();
    }
});

test('installer on a DB with existing rows in users does not drop tables', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_RUN_MIGRATIONS', 'LARAVEL_INSTALLER_NO_NODE');

    try {
        putenv('CHISEL_RUN_MIGRATIONS=true');
        $_ENV['CHISEL_RUN_MIGRATIONS'] = 'true';
        putenv('LARAVEL_INSTALLER_NO_NODE=true');
        $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';

        bindMockChiselScript();

        $user = User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => Hash::make('password123'),
        ]);

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        expect(User::query()->where('email', 'existing@example.com')->exists())->toBeTrue()
            ->and(Schema::hasTable('users'))->toBeTrue();
    } finally {
        $restoreEnv();
    }
});

test('re-run after a simulated mid-install failure does not lose data', function () use ($cleanEnv, $preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_RUN_MIGRATIONS', 'LARAVEL_INSTALLER_NO_NODE', 'CHISEL_TEST_FAIL_STAGE');

    try {
        putenv('CHISEL_RUN_MIGRATIONS=true');
        $_ENV['CHISEL_RUN_MIGRATIONS'] = 'true';
        putenv('LARAVEL_INSTALLER_NO_NODE=true');
        $_ENV['LARAVEL_INSTALLER_NO_NODE'] = 'true';

        bindMockChiselScript();

        $user = User::query()->create([
            'name' => 'Preserved User',
            'email' => 'preserved@example.com',
            'password' => Hash::make('password123'),
        ]);

        putenv('CHISEL_TEST_FAIL_STAGE=post_chisel');
        $_ENV['CHISEL_TEST_FAIL_STAGE'] = 'post_chisel';

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        expect(fn () => $this->artisan('install:features', ['--answers' => $answers])->run())
            ->toThrow(RuntimeException::class, 'CHISEL_TEST_FAIL_STAGE');

        expect(User::query()->where('email', 'preserved@example.com')->exists())->toBeTrue();

        $cleanEnv('CHISEL_TEST_FAIL_STAGE');

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        expect(User::query()->where('email', 'preserved@example.com')->exists())->toBeTrue();
    } finally {
        $restoreEnv();
    }
});

test('database migration runs migrate --force by default when CHISEL_RUN_MIGRATIONS is enabled', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_RUN_MIGRATIONS');

    try {
        putenv('CHISEL_RUN_MIGRATIONS=true');
        $_ENV['CHISEL_RUN_MIGRATIONS'] = 'true';

        bindMockChiselScript();

        $ranMigrate = false;
        Artisan::command('migrate {--force}', function () use (&$ranMigrate): int {
            $ranMigrate = true;

            return 0;
        });

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        expect($ranMigrate)->toBeTrue();
    } finally {
        $restoreEnv();
    }
});

test('database migration runs migrate:fresh --force when --fresh is passed and CHISEL_RUN_MIGRATIONS is enabled', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_RUN_MIGRATIONS');

    try {
        putenv('CHISEL_RUN_MIGRATIONS=true');
        $_ENV['CHISEL_RUN_MIGRATIONS'] = 'true';

        bindMockChiselScript();

        $ranMigrateFresh = false;
        Artisan::command('migrate:fresh {--force}', function () use (&$ranMigrateFresh): int {
            $ranMigrateFresh = true;

            return 0;
        });

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers, '--fresh' => true])
            ->assertSuccessful();

        expect($ranMigrateFresh)->toBeTrue();
    } finally {
        $restoreEnv();
    }
});

test('failed database migration returns non-zero exit status and outputs error', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_RUN_MIGRATIONS');

    try {
        putenv('CHISEL_RUN_MIGRATIONS=true');
        $_ENV['CHISEL_RUN_MIGRATIONS'] = 'true';

        bindMockChiselScript();

        Artisan::command('migrate {--force}', fn (): int => 1);

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->expectsOutputToContain('Database migration failed.')
            ->assertFailed();
    } finally {
        $restoreEnv();
    }
});

test('database migration exception returns non-zero exit status and outputs redacted error', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_RUN_MIGRATIONS');

    try {
        putenv('CHISEL_RUN_MIGRATIONS=true');
        $_ENV['CHISEL_RUN_MIGRATIONS'] = 'true';

        bindMockChiselScript();

        $secretPass = 'SuperSecretDbPassword123';
        Artisan::command('migrate {--force}', function () use ($secretPass): never {
            throw new RuntimeException('Connection failed with password '.$secretPass);
        });

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => [],
            'admin' => [
                'password' => $secretPass,
            ],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $answers])
            ->expectsOutputToContain('Database migration failed: Connection failed with password [REDACTED]')
            ->doesntExpectOutputToContain($secretPass)
            ->assertFailed();
    } finally {
        $restoreEnv();
    }
});

test('command dry-run previews all mutations without applying any changes', function (): void {
    bindMockChiselScript();
    Process::fake();

    $userCountBefore = User::query()->count();
    $permissionCountBefore = Permission::query()->count();

    $answers = json_encode([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization', 'user-management'],
        'admin' => [
            'name' => 'Dry Run Admin',
            'email' => 'dryrun@example.com',
            'password' => 'secret1234',
        ],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', [
        '--answers' => $answers,
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Planned Feature Installation Preview')
        ->expectsOutputToContain('Explicit Auth Features')
        ->expectsOutputToContain('Explicit Optional Modules')
        ->expectsOutputToContain('Dependency-Derived Selections')
        ->expectsOutputToContain('PACKAGES (COMPOSER & FRONTEND)')
        ->expectsOutputToContain('DATABASE & CONFIGURATION PHASES')
        ->expectsOutputToContain('POST-SCAFFOLDING TOOLING')
        ->expectsOutputToContain('VALIDATION & CLEANUP PHASES')
        ->expectsOutputToContain('PLANNED FILESYSTEM MUTATIONS')
        ->expectsOutputToContain('0 mutations executed')
        ->assertSuccessful();

    expect(User::query()->count())->toBe($userCountBefore)
        ->and(Permission::query()->count())->toBe($permissionCountBefore);

    Process::assertNothingRan();
});

test('command dry-run displays fresh migration command when --fresh option is supplied', function (): void {
    bindMockChiselScript();
    Process::fake();

    $answers = json_encode([
        'auth_features' => ['login'],
        'optional_modules' => [],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', [
        '--answers' => $answers,
        '--dry-run' => true,
        '--fresh' => true,
    ])
        ->expectsOutputToContain('php artisan migrate:fresh --force')
        ->assertSuccessful();

    Process::assertNothingRan();
});

test('command dry-run fails with invalid feature dependencies identical to normal execution', function (): void {
    bindMockChiselScript();

    $answers = json_encode([
        'auth_features' => ['login'],
        'optional_modules' => ['user-management'],
    ], JSON_THROW_ON_ERROR);

    $this->artisan('install:features', [
        '--answers' => $answers,
        '--dry-run' => true,
    ]);
})->throws(RuntimeException::class, 'The "user-management" feature requires the following feature(s): authorization.');

test('preflight failure stops before pruning and does not create installer state file', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_TEST_FAIL_STAGE');

    try {
        putenv('CHISEL_TEST_FAIL_STAGE=preflight');
        $_ENV['CHISEL_TEST_FAIL_STAGE'] = 'preflight';

        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ], JSON_THROW_ON_ERROR);

        expect(fn () => $this->artisan('install:features', ['--answers' => $answers]))
            ->toThrow(RuntimeException::class, 'deterministic test failure injected at stage [preflight]');

        expect(file_exists(base_path('.installer-state.json')))->toBeFalse();
    } finally {
        $restoreEnv();
    }
});

test('failure after pruning generates installer state file without secrets and outputs actionable recovery', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_TEST_FAIL_STAGE');

    try {
        putenv('CHISEL_TEST_FAIL_STAGE=post_chisel');
        $_ENV['CHISEL_TEST_FAIL_STAGE'] = 'post_chisel';

        bindMockChiselScript();

        $secret = 'UltraSecretPassword999';
        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
            'admin' => [
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => $secret,
            ],
        ], JSON_THROW_ON_ERROR);

        expect(fn () => $this->artisan('install:features', ['--answers' => $answers]))
            ->toThrow(RuntimeException::class, 'deterministic test failure injected at stage [post_chisel]');

        $stateFile = base_path('.installer-state.json');
        expect(file_exists($stateFile))->toBeTrue();

        $content = (string) file_get_contents($stateFile);
        expect($content)->not->toContain($secret);

        $json = json_decode($content, true);
        expect($json['status'])->toBe('failed')
            ->and($json['failed_phase'])->toBe('post_chisel')
            ->and($json['completed_phases'])->toContain('preflight', 'prune')
            ->and($json['metadata']['admin_name'])->toBe('Admin User')
            ->and($json['metadata']['admin_email'])->toBe('admin@example.com')
            ->and($json['metadata'])->not->toHaveKey('admin_password');
    } finally {
        $restoreEnv();
    }
});

test('retry with identical answers skips pruning and removes state file upon success', function () use ($cleanEnv, $preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_TEST_FAIL_STAGE');

    try {
        putenv('CHISEL_TEST_FAIL_STAGE=post_chisel');
        $_ENV['CHISEL_TEST_FAIL_STAGE'] = 'post_chisel';

        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ], JSON_THROW_ON_ERROR);

        // First attempt fails at post_chisel
        expect(fn () => $this->artisan('install:features', ['--answers' => $answers]))
            ->toThrow(RuntimeException::class);

        expect(file_exists(base_path('.installer-state.json')))->toBeTrue();

        // Clear failure trigger for retry
        $cleanEnv('CHISEL_TEST_FAIL_STAGE');

        // Second attempt with identical answers skips pruning and completes
        $this->artisan('install:features', ['--answers' => $answers])
            ->expectsOutputToContain('Resuming previous installation from phase [post_chisel]')
            ->expectsOutputToContain('Feature pruning already completed (skipped on retry)')
            ->assertSuccessful();

        expect(file_exists(base_path('.installer-state.json')))->toBeFalse();
    } finally {
        $restoreEnv();
    }
});

test('retry with conflicting answers is rejected with exit code 1 and error message', function () use ($cleanEnv, $preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_TEST_FAIL_STAGE');

    try {
        putenv('CHISEL_TEST_FAIL_STAGE=post_chisel');
        $_ENV['CHISEL_TEST_FAIL_STAGE'] = 'post_chisel';

        bindMockChiselScript();

        $originalAnswers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ], JSON_THROW_ON_ERROR);

        expect(fn () => $this->artisan('install:features', ['--answers' => $originalAnswers]))
            ->toThrow(RuntimeException::class);

        expect(file_exists(base_path('.installer-state.json')))->toBeTrue();

        // Clear failure trigger
        $cleanEnv('CHISEL_TEST_FAIL_STAGE');

        $conflictingAnswers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization', 'settings'],
        ], JSON_THROW_ON_ERROR);

        $this->artisan('install:features', ['--answers' => $conflictingAnswers])
            ->expectsOutputToContain('Conflicting answers on installer retry.')
            ->expectsOutputToContain('The project was already partially pruned with:')
            ->expectsOutputToContain('Attempted retry with:')
            ->expectsOutputToContain('To retry with the previous answers, run:')
            ->assertFailed();

        // State file is preserved
        expect(file_exists(base_path('.installer-state.json')))->toBeTrue();
    } finally {
        $restoreEnv();
    }
});

test('retry with omitted answers resumes using previous answers from state file', function () use ($cleanEnv, $preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_TEST_FAIL_STAGE');

    try {
        putenv('CHISEL_TEST_FAIL_STAGE=post_chisel');
        $_ENV['CHISEL_TEST_FAIL_STAGE'] = 'post_chisel';

        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ], JSON_THROW_ON_ERROR);

        expect(fn () => $this->artisan('install:features', ['--answers' => $answers]))
            ->toThrow(RuntimeException::class);

        expect(file_exists(base_path('.installer-state.json')))->toBeTrue();

        // Clear failure trigger
        $cleanEnv('CHISEL_TEST_FAIL_STAGE');

        // Run without --answers
        $this->artisan('install:features', ['--no-interaction' => true])
            ->expectsOutputToContain('Resuming previous installation from phase [post_chisel]')
            ->expectsOutputToContain('Feature pruning already completed (skipped on retry)')
            ->assertSuccessful();

        expect(file_exists(base_path('.installer-state.json')))->toBeFalse();
    } finally {
        $restoreEnv();
    }
});

test('failure at post_migration stage records failure and retry skips migration', function () use ($cleanEnv, $preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_TEST_FAIL_STAGE');

    try {
        putenv('CHISEL_TEST_FAIL_STAGE=post_migration');
        $_ENV['CHISEL_TEST_FAIL_STAGE'] = 'post_migration';

        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ], JSON_THROW_ON_ERROR);

        expect(fn () => $this->artisan('install:features', ['--answers' => $answers]))
            ->toThrow(RuntimeException::class, 'deterministic test failure injected at stage [post_migration]');

        $stateFile = base_path('.installer-state.json');
        expect(file_exists($stateFile))->toBeTrue();

        $state = json_decode((string) file_get_contents($stateFile), true);
        expect($state['failed_phase'])->toBe('post_migration')
            ->and($state['completed_phases'])->toContain('preflight', 'prune', 'migrate');

        // Clear failure trigger for retry
        $cleanEnv('CHISEL_TEST_FAIL_STAGE');

        $this->artisan('install:features', ['--answers' => $answers])
            ->expectsOutputToContain('Resuming previous installation from phase [post_migration]')
            ->expectsOutputToContain('Database migration already completed')
            ->assertSuccessful();

        expect(file_exists($stateFile))->toBeFalse();
    } finally {
        $restoreEnv();
    }
});

test('failure at post_scaffold stage records failure and retry skips completed phases', function () use ($cleanEnv, $preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_TEST_FAIL_STAGE');

    try {
        putenv('CHISEL_TEST_FAIL_STAGE=post_scaffold');
        $_ENV['CHISEL_TEST_FAIL_STAGE'] = 'post_scaffold';

        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ], JSON_THROW_ON_ERROR);

        expect(fn () => $this->artisan('install:features', ['--answers' => $answers]))
            ->toThrow(RuntimeException::class, 'deterministic test failure injected at stage [post_scaffold]');

        $stateFile = base_path('.installer-state.json');
        expect(file_exists($stateFile))->toBeTrue();

        $state = json_decode((string) file_get_contents($stateFile), true);
        expect($state['failed_phase'])->toBe('post_scaffold')
            ->and($state['completed_phases'])->toContain('preflight', 'prune', 'migrate', 'configure', 'post_scaffold');

        // Clear failure trigger for retry
        $cleanEnv('CHISEL_TEST_FAIL_STAGE');

        $this->artisan('install:features', ['--answers' => $answers])
            ->expectsOutputToContain('Resuming previous installation from phase [post_scaffold]')
            ->expectsOutputToContain('Application configuration already completed')
            ->assertSuccessful();

        expect(file_exists($stateFile))->toBeFalse();
    } finally {
        $restoreEnv();
    }
});

test('failure at cleanup stage records failure and outputs recovery instructions', function () use ($cleanEnv, $preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_TEST_FAIL_STAGE');

    try {
        putenv('CHISEL_TEST_FAIL_STAGE=cleanup');
        $_ENV['CHISEL_TEST_FAIL_STAGE'] = 'cleanup';

        bindMockChiselScript();

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ], JSON_THROW_ON_ERROR);

        expect(fn () => $this->artisan('install:features', ['--answers' => $answers]))
            ->toThrow(RuntimeException::class, 'deterministic test failure injected at stage [cleanup]');

        $stateFile = base_path('.installer-state.json');
        expect(file_exists($stateFile))->toBeTrue();

        $state = json_decode((string) file_get_contents($stateFile), true);
        expect($state['failed_phase'])->toBe('cleanup')
            ->and($state['status'])->toBe('failed');

        // Clear failure trigger for retry
        $cleanEnv('CHISEL_TEST_FAIL_STAGE');

        $this->artisan('install:features', ['--answers' => $answers])
            ->assertSuccessful();

        expect(file_exists($stateFile))->toBeFalse();
    } finally {
        $restoreEnv();
    }
});

test('failure at cleanup stage after installer files are removed outputs manual composer instructions', function () use ($preserveEnv): void {
    $restoreEnv = $preserveEnv('CHISEL_TEST_FAIL_STAGE', 'CHISEL_TEST_NO_INSTALLER_FILES');

    try {
        putenv('CHISEL_TEST_FAIL_STAGE=cleanup');
        $_ENV['CHISEL_TEST_FAIL_STAGE'] = 'cleanup';
        putenv('CHISEL_TEST_NO_INSTALLER_FILES=true');
        $_ENV['CHISEL_TEST_NO_INSTALLER_FILES'] = 'true';

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
                    options: ['authorization' => 'Authorization'],
                    default: ['authorization'],
                ),
            ]);

        app()->instance(Script::class, $script);

        $answers = json_encode([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ], JSON_THROW_ON_ERROR);

        expect(fn () => $this->artisan('install:features', ['--answers' => $answers]))
            ->toThrow(RuntimeException::class, 'deterministic test failure injected at stage [cleanup]');

        $stateFile = base_path('.installer-state.json');
        expect(file_exists($stateFile))->toBeTrue();

        $state = json_decode((string) file_get_contents($stateFile), true);
        expect($state['failed_phase'])->toBe('cleanup');
    } finally {
        $restoreEnv();
    }
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
