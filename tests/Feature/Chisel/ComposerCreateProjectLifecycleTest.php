<?php

declare(strict_types=1);

use App\Enums\Role as RoleEnum;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\Process\Process;

function createComposerGitRepositorySource(string $baseTempDir): string
{
    $sourceDir = $baseTempDir.'/git_source';
    mkdir($sourceDir, 0755, true);

    $root = base_path();
    new Process(['git', 'init', '-b', 'main'], $sourceDir)->mustRun();
    new Process(['git', 'config', 'user.email', 'integration-test@example.com'], $sourceDir)->mustRun();
    new Process(['git', 'config', 'user.name', 'Integration Test Runner'], $sourceDir)->mustRun();

    $filesOutput = new Process(['git', '-C', $root, 'ls-files'], $root)->mustRun()->getOutput();
    $files = array_filter(explode("\n", mb_trim($filesOutput)));

    foreach ($files as $file) {
        $source = $root.'/'.$file;
        $dest = $sourceDir.'/'.$file;

        if (! is_file($source)) {
            continue;
        }

        $destDir = dirname($dest);
        if (! is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        copy($source, $dest);
    }

    new Process(['git', 'add', '-A'], $sourceDir)->mustRun();
    new Process(['git', 'commit', '-m', 'Initial commit for composer create-project test'], $sourceDir)->mustRun();

    return $sourceDir;
}

function removeComposerTestDirectory(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    new Process(['rm', '-rf', $dir])->run();
}

/**
 * @param  array<string, mixed>  $answers
 * @param  array<string, string>  $extraEnv
 */
function runComposerCreateProject(
    string $sourceRepoDir,
    string $targetDir,
    array $answers = [],
    array $extraEnv = [],
    int $timeout = 600,
): Process {
    $repoConfig = json_encode([
        'type' => 'vcs',
        'url' => $sourceRepoDir,
    ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    $baseEnv = getenv();
    $env = is_array($baseEnv) ? $baseEnv : [];

    // Clear test-runner environment variables that would override the newly generated project's configuration
    unset($env['APP_KEY']);

    $mergedEnv = array_merge($env, [
        'CHISEL_ANSWERS' => json_encode($answers, JSON_THROW_ON_ERROR),
        'APP_ENV' => 'local',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => $targetDir.'/database/database.sqlite',
        'CACHE_STORE' => 'array',
        'SESSION_DRIVER' => 'array',
        'COMPOSER_NO_INTERACTION' => '1',
    ], $extraEnv);

    $command = [
        'composer',
        'create-project',
        'tooinfinity/infinity-starter-kit',
        $targetDir,
        'dev-main',
        '--repository='.$repoConfig,
        '--remove-vcs',
        '--no-interaction',
    ];

    $process = new Process($command, dirname($targetDir), $mergedEnv, null, $timeout);
    $process->run();

    return $process;
}

/**
 * @param  array<string, string>  $env
 */
function runInCreatedProject(string $dir, array $command, array $env = [], int $timeout = 300): Process
{
    $baseEnv = getenv();
    $envClean = is_array($baseEnv) ? $baseEnv : [];
    unset($envClean['APP_KEY']);

    $mergedEnv = array_merge($envClean, [
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

/**
 * @return array{name: string, install: list<string>, build: list<string>, lint: list<string>}
 */
function detectFrontendPackageManager(string $dir): array
{
    $packageJsonPath = $dir.'/package.json';
    expect(file_exists($packageJsonPath))->toBeTrue('package.json must exist in generated project.');

    $packageJson = json_decode((string) file_get_contents($packageJsonPath), true);
    expect($packageJson)->toBeArray();

    if (file_exists($dir.'/bun.lock') || file_exists($dir.'/bun.lockb')) {
        return [
            'name' => 'bun',
            'install' => ['bun', 'install', '--frozen-lockfile'],
            'build' => ['bun', 'run', 'build'],
            'lint' => ['bun', 'run', 'lint'],
        ];
    }

    if (file_exists($dir.'/pnpm-lock.yaml')) {
        return [
            'name' => 'pnpm',
            'install' => ['pnpm', 'install', '--frozen-lockfile'],
            'build' => ['pnpm', 'run', 'build'],
            'lint' => ['pnpm', 'run', 'lint'],
        ];
    }

    if (file_exists($dir.'/yarn.lock')) {
        return [
            'name' => 'yarn',
            'install' => ['yarn', 'install', '--immutable'],
            'build' => ['yarn', 'run', 'build'],
            'lint' => ['yarn', 'run', 'lint'],
        ];
    }

    return [
        'name' => 'npm',
        'install' => ['npm', 'ci'],
        'build' => ['npm', 'run', 'build'],
        'lint' => ['npm', 'run', 'lint'],
    ];
}

function assertCreatedProjectCleanOfInstallerArtifacts(string $targetDir): void
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
        expect(file_exists($targetDir.'/'.$path))
            ->toBeFalse("Expected installer artifact [{$path}] to be removed from generated project.");
    }

    $composerJson = json_decode((string) file_get_contents($targetDir.'/composer.json'), true);
    expect($composerJson)->toBeArray()
        ->and($composerJson['require-dev']['laravel/chisel'] ?? null)->toBeNull()
        ->and($composerJson['require']['laravel/chisel'] ?? null)->toBeNull();

    $postCreateCmds = $composerJson['scripts']['post-create-project-cmd'] ?? [];
    foreach ($postCreateCmds as $cmd) {
        expect($cmd)->not->toContain('install:features')
            ->and($cmd)->not->toContain('chisel');
    }

    $phpstanContent = (string) file_get_contents($targetDir.'/phpstan.neon');
    expect($phpstanContent)->not->toContain('chisel.php')
        ->and($phpstanContent)->not->toContain('bootstrapFiles:');

    $phpunitContent = (string) file_get_contents($targetDir.'/phpunit.xml');
    expect($phpunitContent)->not->toContain('InstallFeaturesCommand.php')
        ->and($phpunitContent)->not->toContain('tests/Feature/Chisel')
        ->and($phpunitContent)->not->toContain('tests/Unit/Chisel');

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
        if (! is_dir($targetDir.'/'.$scanDir)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($targetDir.'/'.$scanDir, FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            $relativePath = str_replace($targetDir.'/', '', $file->getPathname());

            foreach ($forbiddenTokens as $token) {
                expect($content)->not->toContain(
                    $token,
                    "Found stale reference [{$token}] in [{$relativePath}]"
                );
            }
        }
    }
}

describe('Composer Create-Project Lifecycle E2E Test', function (): void {
    it('executes Scenario A: full feature installation with authorization, admin setup, secret protection, lockfile sync, and frontend verification', function (): void {
        $baseTempDir = sys_get_temp_dir().'/composer_scenario_a_'.uniqid();
        mkdir($baseTempDir, 0755, true);

        $sourceDir = createComposerGitRepositorySource($baseTempDir);
        $targetDir = $baseTempDir.'/target';
        $secretPassword = 'ScenarioAdminSecretPassword!'.bin2hex(random_bytes(8));

        $answers = [
            'auth_features' => [
                'registration',
                'email-verification',
                'two-factor-authentication',
            ],
            'optional_modules' => [
                'authorization',
                'settings',
                'user-management',
                'localization',
                'notifications',
                'audit-trails',
                'reporting',
            ],
            'admin' => [
                'name' => 'Scenario A Administrator',
                'email' => 'admin-scenario-a@example.com',
                'password' => $secretPassword,
            ],
        ];

        try {
            $createProcess = runComposerCreateProject($sourceDir, $targetDir, $answers);

            expect($createProcess->getExitCode())
                ->toBe(0, "composer create-project failed:\nSTDOUT:\n".$createProcess->getOutput()."\nSTDERR:\n".$createProcess->getErrorOutput());

            // Secret protection: password must never appear in composer output, stderr, or log files
            expect($createProcess->getOutput())->not->toContain($secretPassword)
                ->and($createProcess->getErrorOutput())->not->toContain($secretPassword);

            if (is_file($targetDir.'/storage/logs/laravel.log')) {
                expect((string) file_get_contents($targetDir.'/storage/logs/laravel.log'))->not->toContain($secretPassword);
            }

            // Verify generated files do not contain the raw secret password
            $scanDirs = ['app', 'config', 'database', 'routes', 'resources', 'storage/logs'];
            foreach ($scanDirs as $dir) {
                if (! is_dir($targetDir.'/'.$dir)) {
                    continue;
                }

                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($targetDir.'/'.$dir, FilesystemIterator::SKIP_DOTS)
                );

                /** @var SplFileInfo $file */
                foreach ($iterator as $file) {
                    if (! $file->isFile()) {
                        continue;
                    }

                    $content = (string) file_get_contents($file->getPathname());
                    expect($content)->not->toContain($secretPassword);
                }
            }

            // Verify cleanup of all installer artifacts
            assertCreatedProjectCleanOfInstallerArtifacts($targetDir);

            // Verify authorization permissions, Super Admin role, and admin user in generated project database
            $verifyAuthProcess = runInCreatedProject($targetDir, [
                PHP_BINARY,
                '-r',
                sprintf(
                    'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make('.Kernel::class.'::class)->bootstrap(); '
                    .('$role = '.Role::class.'::where("name", '.RoleEnum::class.'::SuperAdmin->value)->first(); ')
                    .('$permCount = '.Permission::class.'::count(); ')
                    .('$user = '.User::class.'::where("email", "admin-scenario-a@example.com")->first(); ')
                    .('$hasRole = $user ? $user->hasRole('.RoleEnum::class.'::SuperAdmin->value) : false; ')
                    .('$passOk = $user ? '.Hash::class.'::check(%s, $user->password) : false; ')
                    .'echo json_encode(["role" => (bool) $role, "permCount" => $permCount, "user" => (bool) $user, "hasRole" => $hasRole, "passOk" => $passOk]);',
                    var_export($secretPassword, true)
                ),
            ]);

            expect($verifyAuthProcess->getExitCode())->toBe(0, $verifyAuthProcess->getErrorOutput());
            $authData = json_decode($verifyAuthProcess->getOutput(), true);
            expect($authData)->toBeArray()
                ->and($authData['role'])->toBeTrue('Super Admin role must exist in generated database.')
                ->and($authData['permCount'])->toBeGreaterThan(0)
                ->and($authData['user'])->toBeTrue('Admin user must exist in generated database.')
                ->and($authData['hasRole'])->toBeTrue('Admin user must have Super Admin role.')
                ->and($authData['passOk'])->toBeTrue('Admin user password must verify correctly.');

            // Verify composer validate and composer install work on generated project independently
            $composerValidate = runInCreatedProject($targetDir, ['composer', 'validate', '--no-check-publish']);
            expect($composerValidate->getExitCode())->toBe(0, $composerValidate->getOutput()."\n".$composerValidate->getErrorOutput());

            $composerInstall = runInCreatedProject($targetDir, ['composer', 'install', '--no-interaction', '--no-scripts']);
            expect($composerInstall->getExitCode())->toBe(0, $composerInstall->getOutput()."\n".$composerInstall->getErrorOutput());

            // Verify php artisan runs independently in generated project
            $artisanList = runInCreatedProject($targetDir, [PHP_BINARY, 'artisan', '--version']);
            expect($artisanList->getExitCode())->toBe(0, $artisanList->getOutput()."\n".$artisanList->getErrorOutput());

            // Verify frontend package manager detection and independent install, build, and lint
            $pm = detectFrontendPackageManager($targetDir);
            expect($pm['name'])->toBe('bun');

            $feInstall = runInCreatedProject($targetDir, $pm['install']);
            expect($feInstall->getExitCode())->toBe(0, $feInstall->getOutput()."\n".$feInstall->getErrorOutput());

            $feBuild = runInCreatedProject($targetDir, $pm['build']);
            expect($feBuild->getExitCode())->toBe(0, $feBuild->getOutput()."\n".$feBuild->getErrorOutput());

            $feLint = runInCreatedProject($targetDir, $pm['lint']);
            expect($feLint->getExitCode())->toBe(0, $feLint->getOutput()."\n".$feLint->getErrorOutput());
        } finally {
            removeComposerTestDirectory($baseTempDir);
        }
    });

    it('executes Scenario B: minimal installation with package pruning, lockfile consistency, build, lint, and boot verification', function (): void {
        $baseTempDir = sys_get_temp_dir().'/composer_scenario_b_'.uniqid();
        mkdir($baseTempDir, 0755, true);

        $sourceDir = createComposerGitRepositorySource($baseTempDir);
        $targetDir = $baseTempDir.'/target';

        $answers = [
            'auth_features' => [],
            'optional_modules' => [],
        ];

        try {
            $createProcess = runComposerCreateProject($sourceDir, $targetDir, $answers);

            expect($createProcess->getExitCode())
                ->toBe(0, "composer create-project failed:\nSTDOUT:\n".$createProcess->getOutput()."\nSTDERR:\n".$createProcess->getErrorOutput());

            assertCreatedProjectCleanOfInstallerArtifacts($targetDir);

            // Verify optional module paths are absent
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
                expect(file_exists($targetDir.'/'.$path))
                    ->toBeFalse("Expected optional feature path [{$path}] to be absent in minimal installation.");
            }

            // Verify disabled Composer and npm packages are absent from manifests and lockfiles
            $composerJson = json_decode((string) file_get_contents($targetDir.'/composer.json'), true);
            expect($composerJson['require']['spatie/laravel-permission'] ?? null)->toBeNull()
                ->and($composerJson['require']['erag/laravel-lang-sync-inertia'] ?? null)->toBeNull()
                ->and($composerJson['require']['spatie/laravel-data'] ?? null)->toBeNull()
                ->and(is_dir($targetDir.'/vendor/spatie/laravel-permission'))->toBeFalse()
                ->and(is_dir($targetDir.'/vendor/spatie/laravel-data'))->toBeFalse();

            $composerLock = json_decode((string) file_get_contents($targetDir.'/composer.lock'), true);
            $lockedNames = array_column(array_merge($composerLock['packages'] ?? [], $composerLock['packages-dev'] ?? []), 'name');
            expect(in_array('spatie/laravel-permission', $lockedNames, true))->toBeFalse()
                ->and(in_array('erag/laravel-lang-sync-inertia', $lockedNames, true))->toBeFalse()
                ->and(in_array('spatie/laravel-data', $lockedNames, true))->toBeFalse()
                ->and(in_array('laravel/chisel', $lockedNames, true))->toBeFalse();

            $packageJson = json_decode((string) file_get_contents($targetDir.'/package.json'), true);
            expect($packageJson['dependencies']['@erag/lang-sync-inertia'] ?? null)->toBeNull()
                ->and($packageJson['devDependencies']['@erag/lang-sync-inertia'] ?? null)->toBeNull();

            // Verify no admin user was created
            $userCountProcess = runInCreatedProject($targetDir, [
                PHP_BINARY,
                '-r',
                'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make('.Kernel::class.'::class)->bootstrap(); echo '.User::class.'::count();',
            ]);
            expect($userCountProcess->getExitCode())->toBe(0, $userCountProcess->getOutput()."\n".$userCountProcess->getErrorOutput())
                ->and(mb_trim($userCountProcess->getOutput()))->toBe('0');

            // Verify composer validate and composer install work on generated project independently
            $composerValidate = runInCreatedProject($targetDir, ['composer', 'validate', '--no-check-publish']);
            expect($composerValidate->getExitCode())->toBe(0, $composerValidate->getOutput()."\n".$composerValidate->getErrorOutput());

            $composerInstall = runInCreatedProject($targetDir, ['composer', 'install', '--no-interaction', '--no-scripts']);
            expect($composerInstall->getExitCode())->toBe(0, $composerInstall->getOutput()."\n".$composerInstall->getErrorOutput());

            // Verify artisan boots cleanly
            $artisanHelp = runInCreatedProject($targetDir, [PHP_BINARY, 'artisan', '--version']);
            expect($artisanHelp->getExitCode())->toBe(0, $artisanHelp->getOutput()."\n".$artisanHelp->getErrorOutput());

            // Verify frontend package manager detection and independent install, build, and lint
            $pm = detectFrontendPackageManager($targetDir);
            expect($pm['name'])->toBe('bun');

            $feInstall = runInCreatedProject($targetDir, $pm['install']);
            expect($feInstall->getExitCode())->toBe(0, $feInstall->getOutput()."\n".$feInstall->getErrorOutput());

            $feBuild = runInCreatedProject($targetDir, $pm['build']);
            expect($feBuild->getExitCode())->toBe(0, $feBuild->getOutput()."\n".$feBuild->getErrorOutput());

            $feLint = runInCreatedProject($targetDir, $pm['lint']);
            expect($feLint->getExitCode())->toBe(0, $feLint->getOutput()."\n".$feLint->getErrorOutput());

            // Verify PHPStan analysis passes on minimal generated project
            $phpstan = runInCreatedProject($targetDir, ['vendor/bin/phpstan', 'analyse', '--no-progress', '--memory-limit=512M']);
            expect($phpstan->getExitCode())->toBe(0, $phpstan->getOutput()."\n".$phpstan->getErrorOutput());
        } finally {
            removeComposerTestDirectory($baseTempDir);
        }
    });

    it('executes Scenario C: authorization enabled without admin credentials results in zero users and Super Admin role', function (): void {
        $baseTempDir = sys_get_temp_dir().'/composer_scenario_c_'.uniqid();
        mkdir($baseTempDir, 0755, true);

        $sourceDir = createComposerGitRepositorySource($baseTempDir);
        $targetDir = $baseTempDir.'/target';

        $answers = [
            'auth_features' => [
                'registration',
                'email-verification',
                'two-factor-authentication',
            ],
            'optional_modules' => [
                'authorization',
                'settings',
                'user-management',
            ],
        ];

        try {
            $createProcess = runComposerCreateProject($sourceDir, $targetDir, $answers);

            expect($createProcess->getExitCode())
                ->toBe(0, "composer create-project failed:\nSTDOUT:\n".$createProcess->getOutput()."\nSTDERR:\n".$createProcess->getErrorOutput());

            // Verify cleanup of all installer artifacts
            assertCreatedProjectCleanOfInstallerArtifacts($targetDir);

            // Verify authorization is configured but zero users exist
            $verifyAuthProcess = runInCreatedProject($targetDir, [
                PHP_BINARY,
                '-r',
                'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make('.Kernel::class.'::class)->bootstrap(); '
                    .('$role = '.Role::class.'::where("name", '.RoleEnum::class.'::SuperAdmin->value)->first(); ')
                    .('$permCount = '.Permission::class.'::count(); ')
                    .('$userCount = '.User::class.'::count(); ')
                    .'echo json_encode(["role" => (bool) $role, "permCount" => $permCount, "userCount" => $userCount]);',
            ]);

            expect($verifyAuthProcess->getExitCode())->toBe(0, $verifyAuthProcess->getErrorOutput());
            $authData = json_decode($verifyAuthProcess->getOutput(), true);
            expect($authData)->toBeArray()
                ->and($authData['role'])->toBeTrue('Super Admin role must exist even without admin credentials.')
                ->and($authData['permCount'])->toBeGreaterThan(0)
                ->and($authData['userCount'])->toBe(0, 'Zero users must exist when no admin credentials were supplied.');

            // Verify composer validate and install
            $composerValidate = runInCreatedProject($targetDir, ['composer', 'validate', '--no-check-publish']);
            expect($composerValidate->getExitCode())->toBe(0, $composerValidate->getOutput()."\n".$composerValidate->getErrorOutput());

            $composerInstall = runInCreatedProject($targetDir, ['composer', 'install', '--no-interaction', '--no-scripts']);
            expect($composerInstall->getExitCode())->toBe(0, $composerInstall->getOutput()."\n".$composerInstall->getErrorOutput());

            // Verify artisan boots
            $artisanVersion = runInCreatedProject($targetDir, [PHP_BINARY, 'artisan', '--version']);
            expect($artisanVersion->getExitCode())->toBe(0, $artisanVersion->getOutput()."\n".$artisanVersion->getErrorOutput());

            // Verify frontend install, build, and lint
            $pm = detectFrontendPackageManager($targetDir);
            $feInstall = runInCreatedProject($targetDir, $pm['install']);
            expect($feInstall->getExitCode())->toBe(0, $feInstall->getOutput()."\n".$feInstall->getErrorOutput());

            $feBuild = runInCreatedProject($targetDir, $pm['build']);
            expect($feBuild->getExitCode())->toBe(0, $feBuild->getOutput()."\n".$feBuild->getErrorOutput());

            $feLint = runInCreatedProject($targetDir, $pm['lint']);
            expect($feLint->getExitCode())->toBe(0, $feLint->getOutput()."\n".$feLint->getErrorOutput());
        } finally {
            removeComposerTestDirectory($baseTempDir);
        }
    });

    it('executes Scenario D: intentional installer failure produces non-zero exit and preserves installer artifacts', function (): void {
        $baseTempDir = sys_get_temp_dir().'/composer_scenario_d_'.uniqid();
        mkdir($baseTempDir, 0755, true);

        $sourceDir = createComposerGitRepositorySource($baseTempDir);
        $targetDir = $baseTempDir.'/target';

        $answers = [
            'auth_features' => ['registration'],
            'optional_modules' => ['authorization'],
        ];

        try {
            // Inject a deterministic failure via CHISEL_TEST_FAIL_STAGE environment variable.
            // This causes InstallFeaturesCommand to throw after chisel mutations but before cleanup.
            $createProcess = runComposerCreateProject(
                $sourceDir,
                $targetDir,
                $answers,
                ['CHISEL_TEST_FAIL_STAGE' => 'post_chisel'],
            );

            // The installer must report failure with a non-zero exit code
            expect($createProcess->getExitCode())
                ->not->toBe(0, 'Installer must fail when CHISEL_TEST_FAIL_STAGE is set.');

            // The failure output must be observable
            $combinedOutput = $createProcess->getOutput().$createProcess->getErrorOutput();
            expect($combinedOutput)->toContain('CHISEL_TEST_FAIL_STAGE');

            // Installer artifacts must NOT have been cleaned up on failure — they remain for diagnosis
            // The chisel.php file should still exist because cleanup only runs on success
            expect(file_exists($targetDir.'/chisel.php'))->toBeTrue(
                'chisel.php must remain after installer failure for diagnosis.'
            );
            expect(file_exists($targetDir.'/chisel-paths.php'))->toBeTrue(
                'chisel-paths.php must remain after installer failure for diagnosis.'
            );
            expect(file_exists($targetDir.'/app/Console/Commands/InstallFeaturesCommand.php'))->toBeTrue(
                'InstallFeaturesCommand must remain after installer failure for diagnosis.'
            );

            // The installer must not claim successful completion in output
            expect($combinedOutput)->not->toContain('Admin setup complete.');

            // Composer post-create-project-cmd and package references must NOT have been removed on failure
            $failedComposerJson = (string) file_get_contents($targetDir.'/composer.json');
            expect($failedComposerJson)->toContain('install:features')
                ->and($failedComposerJson)->toContain('laravel/chisel');
        } finally {
            removeComposerTestDirectory($baseTempDir);
        }
    });
});
