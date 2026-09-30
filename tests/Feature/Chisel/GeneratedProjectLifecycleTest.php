<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

function createRealGeneratedProjectFixture(): string
{
    $baseTempDir = storage_path('framework/testing');
    if (! is_dir($baseTempDir)) {
        mkdir($baseTempDir, 0755, true);
    }

    $tempDir = $baseTempDir.'/generated_project_'.uniqid();
    mkdir($tempDir, 0755, true);
    mkdir($tempDir.'/.git', 0755, true);

    $root = base_path();
    $filesOutput = shell_exec('git -C '.escapeshellarg($root).' ls-files') ?: '';
    $files = array_filter(explode("\n", mb_trim($filesOutput)));

    foreach ($files as $file) {
        $source = $root.'/'.$file;
        $dest = $tempDir.'/'.$file;

        if (! is_file($source)) {
            continue;
        }

        $destDir = dirname($dest);
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        copy($source, $dest);
    }

    foreach ([
        'bootstrap/cache',
        'storage/app',
        'storage/framework/cache',
        'storage/framework/sessions',
        'storage/framework/testing',
        'storage/framework/views',
        'storage/logs',
        'database',
    ] as $dir) {
        if (! is_dir($tempDir.'/'.$dir)) {
            mkdir($tempDir.'/'.$dir, 0755, true);
        }
    }

    copy($root.'/.env.example', $tempDir.'/.env');
    touch($tempDir.'/database/database.sqlite');

    $envContent = (string) file_get_contents($tempDir.'/.env');
    $envContent = (string) preg_replace('/^#?\s*DB_CONNECTION=.*$/m', 'DB_CONNECTION=sqlite', $envContent);
    $envContent = (string) preg_replace('/^#?\s*DB_DATABASE=.*$/m', 'DB_DATABASE='.$tempDir.'/database/database.sqlite', $envContent);
    file_put_contents($tempDir.'/.env', $envContent);

    // Copy vendor and node_modules using copy-on-write reflinks so Composer and Bun
    // can mutate lockfiles and packages in complete isolation.
    new Process(['cp', '-a', '--reflink=auto', $root.'/vendor', $tempDir.'/vendor'])
        ->setTimeout(120)
        ->mustRun();

    if (is_dir($root.'/node_modules')) {
        new Process(['cp', '-a', '--reflink=auto', $root.'/node_modules', $tempDir.'/node_modules'])
            ->setTimeout(120)
            ->mustRun();
    }

    // Prepare SQLite database schema for real authorization/admin setup during installation.
    runInGeneratedProject($tempDir, [PHP_BINARY, 'artisan', 'key:generate', '--no-interaction'], [], 60)->mustRun();
    runInGeneratedProject($tempDir, [PHP_BINARY, 'artisan', 'migrate', '--force', '--no-interaction'], [], 60)->mustRun();

    return $tempDir;
}

function removeRealGeneratedProjectFixture(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    new Process(['rm', '-rf', $dir])
        ->setTimeout(60)
        ->run();
}

/**
 * @param  array<string, string>  $env
 */
function runInGeneratedProject(string $dir, array $command, array $env = [], int $timeout = 300): Process
{
    $baseEnv = getenv();
    $mergedEnv = array_merge(is_array($baseEnv) ? $baseEnv : [], [
        'APP_ENV' => 'local',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $dir.'/database/database.sqlite',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
    ], $env);

    $process = new Process($command, $dir, $mergedEnv, null, $timeout);
    $process->run();

    return $process;
}

function assertGeneratedProjectCleanOfInstallerArtifacts(string $tempDir): void
{
    $removedPaths = [
        'chisel.php',
        'chisel-paths.php',
        'app/Console/Commands/InstallFeaturesCommand.php',
        'app/Console/Commands/SetupAuthorizationCommand.php',
        'app/Console/Commands/SetupAdminUserCommand.php',
        'tests/Feature/Authorization/SetupAdminUserCommandTest.php',
        'tests/Feature/Chisel',
        'tests/Unit/Chisel',
    ];

    foreach ($removedPaths as $path) {
        expect(file_exists($tempDir.'/'.$path))
            ->toBeFalse("Expected installer artifact [{$path}] to be removed from generated project.");
    }

    $composerJson = json_decode((string) file_get_contents($tempDir.'/composer.json'), true);
    expect($composerJson)->toBeArray()
        ->and($composerJson['require-dev']['laravel/chisel'] ?? null)->toBeNull();

    $postCreateCmds = $composerJson['scripts']['post-create-project-cmd'] ?? [];
    foreach ($postCreateCmds as $cmd) {
        expect($cmd)->not->toContain('install:features')
            ->and($cmd)->not->toContain('chisel');
    }

    $phpstanContent = (string) file_get_contents($tempDir.'/phpstan.neon');
    expect($phpstanContent)->not->toContain('chisel.php')
        ->and($phpstanContent)->not->toContain('bootstrapFiles:');

    $phpunitContent = (string) file_get_contents($tempDir.'/phpunit.xml');
    expect($phpunitContent)->not->toContain('InstallFeaturesCommand.php');

    // Recursively scan generated project files for stale references
    $forbiddenTokens = [
        'InstallFeaturesCommand',
        'SetupAuthorizationCommand',
        'SetupAdminUserCommand',
        'chisel.php',
        'chisel-paths.php',
        'Laravel\\Chisel',
        'laravel/chisel',
        'install:features',
        '@chisel-',
        '@end-chisel-',
    ];

    $scanDirs = ['app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes', 'tests'];
    foreach ($scanDirs as $scanDir) {
        if (! is_dir($tempDir.'/'.$scanDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir.'/'.$scanDir, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            $relativePath = str_replace($tempDir.'/', '', $file->getPathname());

            foreach ($forbiddenTokens as $token) {
                expect($content)->not->toContain(
                    $token,
                    "Found stale reference [{$token}] in [{$relativePath}]"
                );
            }
        }
    }
}

describe('Real Generated Project E2E Lifecycle', function (): void {
    it('completes full installation with authorization, admin setup, composer/bun verification, and secret protection', function (): void {
        $tempDir = createRealGeneratedProjectFixture();
        $secretPassword = 'SuperSecretAdminPass!98765';

        try {
            $installProcess = runInGeneratedProject($tempDir, [
                PHP_BINARY,
                'artisan',
                'install:features',
                '--answers='.json_encode([
                    'auth_features' => [
                        'email-verification',
                        'two-factor-authentication',
                    ],
                    'optional_modules' => [
                        'user-management',
                        'authorization',
                        'audit-trails',
                        'notifications',
                        'reporting',
                        'localization',
                    ],
                ], JSON_THROW_ON_ERROR),
                '--admin-name=Integration Admin',
                '--admin-email=integration-admin@example.com',
                '--admin-password='.$secretPassword,
                '--no-interaction',
            ]);

            expect($installProcess->getExitCode())->toBe(0, $installProcess->getOutput()."\n".$installProcess->getErrorOutput());

            // Secret protection check: password must never appear in stdout, stderr, or logs
            expect($installProcess->getOutput())->not->toContain($secretPassword)
                ->and($installProcess->getErrorOutput())->not->toContain($secretPassword);

            if (is_file($tempDir.'/storage/logs/laravel.log')) {
                expect((string) file_get_contents($tempDir.'/storage/logs/laravel.log'))->not->toContain($secretPassword);
            }

            assertGeneratedProjectCleanOfInstallerArtifacts($tempDir);

            // Verify authorization permissions, Super Admin role, and admin user in generated project DB
            $verifyAuthProcess = runInGeneratedProject($tempDir, [
                PHP_BINARY,
                '-r',
                sprintf(
                    'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make('.Kernel::class.'::class)->bootstrap(); '
                    .('$role = '.Role::class.'::where("name", '.App\Enums\Role::class.'::SuperAdmin->value)->first(); ')
                    .('$permCount = '.Permission::class.'::count(); ')
                    .('$user = '.User::class.'::where("email", "integration-admin@example.com")->first(); ')
                    .('$hasRole = $user ? $user->hasRole('.App\Enums\Role::class.'::SuperAdmin->value) : false; ')
                    .('$passOk = $user ? '.Hash::class.'::check(%s, $user->password) : false; ')
                    .'echo json_encode(["role" => (bool) $role, "permCount" => $permCount, "user" => (bool) $user, "hasRole" => $hasRole, "passOk" => $passOk]);',
                    var_export($secretPassword, true)
                ),
            ]);

            expect($verifyAuthProcess->getExitCode())->toBe(0, $verifyAuthProcess->getErrorOutput());
            $authData = json_decode($verifyAuthProcess->getOutput(), true);
            expect($authData)->toBeArray()
                ->and($authData['role'])->toBeTrue()
                ->and($authData['permCount'])->toBeGreaterThan(0)
                ->and($authData['user'])->toBeTrue()
                ->and($authData['hasRole'])->toBeTrue()
                ->and($authData['passOk'])->toBeTrue();

            // Composer validate and install
            $composerValidate = runInGeneratedProject($tempDir, ['composer', 'validate', '--no-check-publish']);
            expect($composerValidate->getExitCode())->toBe(0, $composerValidate->getOutput()."\n".$composerValidate->getErrorOutput());

            $composerInstall = runInGeneratedProject($tempDir, ['composer', 'install', '--no-interaction', '--no-scripts']);
            expect($composerInstall->getExitCode())->toBe(0, $composerInstall->getOutput()."\n".$composerInstall->getErrorOutput());

            // Frontend install, build, and lint
            $bunInstall = runInGeneratedProject($tempDir, ['bun', 'install', '--frozen-lockfile']);
            expect($bunInstall->getExitCode())->toBe(0, $bunInstall->getOutput()."\n".$bunInstall->getErrorOutput());

            $bunBuild = runInGeneratedProject($tempDir, ['bun', 'run', 'build']);
            expect($bunBuild->getExitCode())->toBe(0, $bunBuild->getOutput()."\n".$bunBuild->getErrorOutput());

            $bunLint = runInGeneratedProject($tempDir, ['bun', 'run', 'lint']);
            expect($bunLint->getExitCode())->toBe(0, $bunLint->getOutput()."\n".$bunLint->getErrorOutput());

            // PHPStan analysis on generated project
            $phpstan = runInGeneratedProject($tempDir, ['vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=512M']);
            expect($phpstan->getExitCode())->toBe(0, $phpstan->getOutput()."\n".$phpstan->getErrorOutput());
        } finally {
            removeRealGeneratedProjectFixture($tempDir);
        }
    });

    it('completes minimal installation (core auth only) with real package pruning, lockfile consistency, build, lint, PHPStan, and Pest', function (): void {
        $tempDir = createRealGeneratedProjectFixture();

        try {
            $installProcess = runInGeneratedProject($tempDir, [
                PHP_BINARY,
                'artisan',
                'install:features',
                '--answers='.json_encode([
                    'auth_features' => [],
                    'optional_modules' => [],
                ], JSON_THROW_ON_ERROR),
                '--no-interaction',
            ]);

            expect($installProcess->getExitCode())->toBe(0, $installProcess->getOutput()."\n".$installProcess->getErrorOutput());

            assertGeneratedProjectCleanOfInstallerArtifacts($tempDir);

            // Verify optional modules are absent
            $absentPaths = [
                'app/Models/AuditTrail.php',
                'app/Models/DatabaseNotification.php',
                'app/Http/Controllers/Users',
                'app/Http/Controllers/Roles',
                'app/Http/Controllers/AuditTrails',
                'app/Http/Controllers/Notifications',
                'app/Http/Controllers/Reporting',
                'resources/js/pages/users',
                'resources/js/pages/roles',
                'resources/js/pages/audit-trails',
                'resources/js/pages/notifications',
                'resources/js/pages/reporting',
                'resources/js/components/notifications',
                'resources/js/types/audit-trail.ts',
                'resources/js/types/notification.ts',
                'resources/js/types/reporting.ts',
                'config/permission.php',
                'config/data.php',
            ];

            foreach ($absentPaths as $path) {
                expect(file_exists($tempDir.'/'.$path))
                    ->toBeFalse("Expected optional feature path [{$path}] to be absent in minimal installation.");
            }

            // Verify disabled Composer and npm packages are absent from manifests and lockfiles
            $composerJson = json_decode((string) file_get_contents($tempDir.'/composer.json'), true);
            expect($composerJson['require']['spatie/laravel-permission'] ?? null)->toBeNull()
                ->and($composerJson['require']['erag/laravel-lang-sync-inertia'] ?? null)->toBeNull()
                ->and($composerJson['require']['spatie/laravel-data'] ?? null)->toBeNull()
                ->and(is_dir($tempDir.'/vendor/spatie/laravel-permission'))->toBeFalse()
                ->and(is_dir($tempDir.'/vendor/spatie/laravel-data'))->toBeFalse();

            $packageJson = json_decode((string) file_get_contents($tempDir.'/package.json'), true);
            expect($packageJson['dependencies']['@erag/lang-sync-inertia'] ?? null)->toBeNull()
                ->and($packageJson['devDependencies']['@erag/lang-sync-inertia'] ?? null)->toBeNull();

            // Verify no admin user or authorization setup ran
            $userCountProcess = runInGeneratedProject($tempDir, [
                PHP_BINARY,
                '-r',
                'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make('.Kernel::class.'::class)->bootstrap(); echo '.User::class.'::count();',
            ]);
            expect($userCountProcess->getExitCode())->toBe(0, $userCountProcess->getOutput()."\n".$userCountProcess->getErrorOutput())
                ->and(mb_trim($userCountProcess->getOutput()))->toBe('0');

            // Verify composer validate and composer install succeed with updated composer.lock
            $composerValidate = runInGeneratedProject($tempDir, ['composer', 'validate', '--no-check-publish']);
            expect($composerValidate->getExitCode())->toBe(0, $composerValidate->getOutput()."\n".$composerValidate->getErrorOutput());

            $composerInstall = runInGeneratedProject($tempDir, ['composer', 'install', '--no-interaction', '--no-scripts']);
            expect($composerInstall->getExitCode())->toBe(0, $composerInstall->getOutput()."\n".$composerInstall->getErrorOutput());

            // Verify frontend install, build, and lint succeed
            $bunInstall = runInGeneratedProject($tempDir, ['bun', 'install', '--frozen-lockfile']);
            expect($bunInstall->getExitCode())->toBe(0, $bunInstall->getOutput()."\n".$bunInstall->getErrorOutput());

            $bunBuild = runInGeneratedProject($tempDir, ['bun', 'run', 'build']);
            expect($bunBuild->getExitCode())->toBe(0, $bunBuild->getOutput()."\n".$bunBuild->getErrorOutput());

            $bunLint = runInGeneratedProject($tempDir, ['bun', 'run', 'lint']);
            expect($bunLint->getExitCode())->toBe(0, $bunLint->getOutput()."\n".$bunLint->getErrorOutput());

            // Verify PHPStan and core Pest tests pass in minimal generated project
            $phpstan = runInGeneratedProject($tempDir, ['vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=512M']);
            expect($phpstan->getExitCode())->toBe(0, $phpstan->getOutput()."\n".$phpstan->getErrorOutput());

            $pest = runInGeneratedProject($tempDir, [
                'vendor/bin/pest',
                'tests/Feature/Controllers/SessionControllerTest.php',
                'tests/Feature/Controllers/UserProfileControllerTest.php',
                'tests/Unit/Actions/UpdateUserTest.php',
            ], ['APP_ENV' => 'testing']);
            expect($pest->getExitCode())->toBe(0, $pest->getOutput()."\n".$pest->getErrorOutput());
        } finally {
            removeRealGeneratedProjectFixture($tempDir);
        }
    });

    it('completes dependency-chain installation (reporting + dependencies) in LARAVEL_INSTALLER_NO_NODE=true mode and remains installable later', function (): void {
        $tempDir = createRealGeneratedProjectFixture();

        try {
            $installProcess = runInGeneratedProject(
                $tempDir,
                [
                    PHP_BINARY,
                    'artisan',
                    'install:features',
                    '--answers='.json_encode([
                        'auth_features' => [],
                        'optional_modules' => [
                            'authorization',
                            'user-management',
                            'audit-trails',
                            'reporting',
                        ],
                    ], JSON_THROW_ON_ERROR),
                    '--no-interaction',
                ],
                ['LARAVEL_INSTALLER_NO_NODE' => 'true']
            );

            expect($installProcess->getExitCode())->toBe(0, $installProcess->getOutput()."\n".$installProcess->getErrorOutput())
                ->and($installProcess->getOutput())->not->toContain('Installing dependencies')
                ->and($installProcess->getOutput())->not->toContain('Building assets')
                ->and($installProcess->getOutput())->not->toContain('Linting frontend assets');

            assertGeneratedProjectCleanOfInstallerArtifacts($tempDir);

            // Dependent features must be present
            expect(is_file($tempDir.'/app/Http/Controllers/Reporting/ReportIndexController.php'))->toBeTrue()
                ->and(is_file($tempDir.'/app/Models/AuditTrail.php'))->toBeTrue()
                ->and(is_file($tempDir.'/app/Http/Controllers/Users/UserController.php'))->toBeTrue()
                ->and(is_file($tempDir.'/config/permission.php'))->toBeTrue();

            // Disabled features must be removed
            expect(is_file($tempDir.'/app/Models/DatabaseNotification.php'))->toBeFalse()
                ->and(is_file($tempDir.'/app/Http/Middleware/SetLocale.php'))->toBeFalse();

            // package.json must be valid and have disabled feature packages removed even in NO_NODE mode
            $packageJson = json_decode((string) file_get_contents($tempDir.'/package.json'), true);
            expect($packageJson)->toBeArray()
                ->and($packageJson['dependencies']['@erag/lang-sync-inertia'] ?? null)->toBeNull();

            // Permissions and Super Admin role must exist
            $verifyRoleProcess = runInGeneratedProject($tempDir, [
                PHP_BINARY,
                '-r',
                'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make('.Kernel::class.'::class)->bootstrap(); '
                .('echo '.Role::class.'::where("name", '.App\Enums\Role::class.'::SuperAdmin->value)->exists() ? "yes" : "no";'),
            ]);
            expect(mb_trim($verifyRoleProcess->getOutput()))->toBe('yes');

            // Verify the generated project remains installable and buildable later
            $composerValidate = runInGeneratedProject($tempDir, ['composer', 'validate', '--no-check-publish']);
            expect($composerValidate->getExitCode())->toBe(0, $composerValidate->getOutput()."\n".$composerValidate->getErrorOutput());

            $composerInstall = runInGeneratedProject($tempDir, ['composer', 'install', '--no-interaction', '--no-scripts']);
            expect($composerInstall->getExitCode())->toBe(0, $composerInstall->getOutput()."\n".$composerInstall->getErrorOutput());

            $bunInstall = runInGeneratedProject($tempDir, ['bun', 'install']);
            expect($bunInstall->getExitCode())->toBe(0, $bunInstall->getOutput()."\n".$bunInstall->getErrorOutput());

            $wayfinder = runInGeneratedProject($tempDir, [PHP_BINARY, 'artisan', 'wayfinder:generate', '--with-form']);
            expect($wayfinder->getExitCode())->toBe(0, $wayfinder->getOutput()."\n".$wayfinder->getErrorOutput());

            $bunBuild = runInGeneratedProject($tempDir, ['bun', 'run', 'build']);
            expect($bunBuild->getExitCode())->toBe(0, $bunBuild->getOutput()."\n".$bunBuild->getErrorOutput());

            $bunLint = runInGeneratedProject($tempDir, ['bun', 'run', 'lint']);
            expect($bunLint->getExitCode())->toBe(0, $bunLint->getOutput()."\n".$bunLint->getErrorOutput());

            $phpstan = runInGeneratedProject($tempDir, ['vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=512M']);
            expect($phpstan->getExitCode())->toBe(0, $phpstan->getOutput()."\n".$phpstan->getErrorOutput());
        } finally {
            removeRealGeneratedProjectFixture($tempDir);
        }
    });
});
