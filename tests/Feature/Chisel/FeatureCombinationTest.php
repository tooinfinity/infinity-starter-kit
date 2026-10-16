<?php

declare(strict_types=1);

/**
 * Creates an isolated sandbox of git-tracked files, executes chisel.php, and yields sandbox directory.
 *
 * @param  array<string, mixed>  $answers
 * @param  (Closure(string): void)|null  $beforeRun
 * @return array{dir: string, cleanup: Closure(): void}
 */
function runChiselSandbox(array $answers, ?Closure $beforeRun = null): array
{
    $tempDir = sys_get_temp_dir().'/chisel_comb_'.uniqid();
    mkdir($tempDir, 0777, true);

    $files = explode("\n", mb_trim((string) shell_exec('git ls-files -c -o --exclude-standard')));
    foreach ($files as $file) {
        if ($file === '' || $file === 'composer.lock' || ! file_exists(base_path($file))) {
            continue;
        }

        $target = $tempDir.'/'.$file;
        @mkdir(dirname($target), 0777, true);
        copy(base_path($file), $target);
    }

    @symlink(base_path('vendor'), $tempDir.'/vendor');

    if ($beforeRun instanceof Closure) {
        $beforeRun($tempDir);
    }

    putenv('LARAVEL_INSTALLER_NO_NODE=true');
    $GLOBALS['_ENV']['LARAVEL_INSTALLER_NO_NODE'] = 'true';

    try {
        $script = require $tempDir.'/chisel.php';
        $script->chisel($answers);

        /** @var array<string, mixed> $paths */
        $paths = require $tempDir.'/chisel-paths.php';
        chiselCleanup($tempDir, $paths);
    } finally {
        putenv('LARAVEL_INSTALLER_NO_NODE');
        unset($GLOBALS['_ENV']['LARAVEL_INSTALLER_NO_NODE'], $GLOBALS['_SERVER']['LARAVEL_INSTALLER_NO_NODE']);
    }

    return [
        'dir' => $tempDir,
        'cleanup' => function () use ($tempDir): void {
            exec('rm -rf '.escapeshellarg($tempDir));
        },
    ];
}

/**
 * Asserts all PHP files in directory pass syntax check.
 */
function assertPhpSyntaxValid(string $directory): void
{
    $rdi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    $syntaxErrors = [];

    foreach ($rdi as $file) {
        if ($file->isFile() && $file->getExtension() === 'php' && ! str_contains($file->getPathname(), '.blade.php')) {
            $output = [];
            $code = 0;
            exec('php -l '.escapeshellarg($file->getPathname()).' 2>&1', $output, $code);
            if ($code !== 0) {
                $syntaxErrors[] = $file->getPathname().': '.implode(' ', $output);
            }
        }
    }

    expect($syntaxErrors)->toBeEmpty('Found PHP syntax errors in sandbox: '.implode("\n", $syntaxErrors));
}

/**
 * Asserts no remaining @chisel or @end-chisel markers exist in source or test files.
 */
function assertNoOrphanedMarkersInSandbox(string $directory): void
{
    $rdi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    $filesWithMarkers = [];

    foreach ($rdi as $file) {
        if ($file->isFile() && ! str_starts_with($file->getFilename(), '.') && $file->getFilename() !== 'README.md') {
            $content = (string) file_get_contents($file->getPathname());
            if (str_contains($content, '@chisel-') || str_contains($content, '@end-chisel-')) {
                $filesWithMarkers[] = str_replace($directory.'/', '', $file->getPathname());
            }
        }
    }

    expect($filesWithMarkers)->toBeEmpty('Found unhandled chisel markers in: '.implode(', ', $filesWithMarkers));
}

it('handles all features enabled', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor'],
        'optional_modules' => ['authorization', 'settings', 'user-management', 'localization', 'notifications', 'audit-trails', 'reporting'],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Enums/Permission.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Models/Setting.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/Users/UserController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LocaleController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/NotificationController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Models/AuditTrail.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/Reporting/AuditReportController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Actions/CreateUser.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/RegisterController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LoginController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LogoutController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/UserEmailVerificationController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/UserTwoFactorAuthenticationController.php'))->toBeTrue();

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true);
        expect(isset($composer['require']['spatie/laravel-permission']))->toBeTrue()
            ->and(isset($composer['require']['spatie/laravel-data']))->toBeTrue()
            ->and(isset($composer['require']['erag/laravel-lang-sync-inertia']))->toBeTrue();

        $packageJson = json_decode((string) file_get_contents($dir.'/package.json'), true);
        expect(isset($packageJson['dependencies']['@erag/lang-sync-inertia']))->toBeTrue();

        expect(file_exists($dir.'/chisel.php'))->toBeFalse()
            ->and(file_exists($dir.'/chisel-paths.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Console/Commands/InstallFeaturesCommand.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Console/Commands/SetupAuthorizationCommand.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Console/Commands/SetupAdminUserCommand.php'))->toBeFalse()
            ->and(file_exists($dir.'/tests/Feature/Chisel'))->toBeFalse()
            ->and(file_exists($dir.'/tests/Unit/Chisel'))->toBeFalse();

        // Verify composer.json cleanup
        expect(isset($composer['require']['laravel/chisel']))->toBeFalse();
        $postCreate = $composer['scripts']['post-create-project-cmd'] ?? [];
        $installFeaturesInHook = array_filter($postCreate, fn ($cmd): bool => is_string($cmd) && str_contains($cmd, 'install:features'));
        expect($installFeaturesInHook)->toBeEmpty();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles all optional modules disabled and all auth features disabled', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => [],
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Enums/Permission.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Models/Setting.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/Users/UserController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/LocaleController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/NotificationController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Models/AuditTrail.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/Reporting/AuditReportController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Actions/CreateUser.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/UserEmailVerificationController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/UserTwoFactorAuthenticationController.php'))->toBeFalse();

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true);
        expect(isset($composer['require']['spatie/laravel-permission']))->toBeFalse()
            ->and(isset($composer['require']['spatie/laravel-data']))->toBeFalse()
            ->and(isset($composer['require']['erag/laravel-lang-sync-inertia']))->toBeFalse();

        $packageJson = json_decode((string) file_get_contents($dir.'/package.json'), true);
        expect(isset($packageJson['dependencies']['@erag/lang-sync-inertia']))->toBeFalse();

        // Verify installer cleanup
        expect(file_exists($dir.'/chisel.php'))->toBeFalse()
            ->and(file_exists($dir.'/chisel-paths.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Console/Commands/InstallFeaturesCommand.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Console/Commands/SetupAuthorizationCommand.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Console/Commands/SetupAdminUserCommand.php'))->toBeFalse();

        // Verify composer.json cleanup
        expect(isset($composer['require']['laravel/chisel']))->toBeFalse();
        $postCreate = $composer['scripts']['post-create-project-cmd'] ?? [];
        $installFeaturesInHook = array_filter($postCreate, fn ($cmd): bool => is_string($cmd) && str_contains($cmd, 'install:features'));
        expect($installFeaturesInHook)->toBeEmpty();

        // Verify PHPStan config no longer references chisel.php
        if (file_exists($dir.'/phpstan.neon')) {
            $neonContent = (string) file_get_contents($dir.'/phpstan.neon');
            expect($neonContent)->not->toContain('chisel.php');
        }

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles authorization alone', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization'],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Enums/Permission.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Enums/Role.php'))->toBeTrue()
            ->and(file_exists($dir.'/config/permission.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Models/Setting.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/Users/UserController.php'))->toBeFalse();

        $permissionContent = (string) file_get_contents($dir.'/app/Enums/Permission.php');
        expect($permissionContent)->toContain('AuthorizationManage')
            ->and($permissionContent)->not->toContain('SettingsManage')
            ->and($permissionContent)->not->toContain('ReportsView');

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true);
        expect(isset($composer['require']['spatie/laravel-permission']))->toBeTrue()
            ->and(isset($composer['require']['spatie/laravel-data']))->toBeFalse()
            ->and(isset($composer['require']['erag/laravel-lang-sync-inertia']))->toBeFalse();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles localization alone', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['localization'],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/LocaleController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Enums/Permission.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Models/Setting.php'))->toBeFalse();

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true);
        expect(isset($composer['require']['erag/laravel-lang-sync-inertia']))->toBeTrue()
            ->and(isset($composer['require']['spatie/laravel-permission']))->toBeFalse()
            ->and(isset($composer['require']['spatie/laravel-data']))->toBeFalse();

        $packageJson = json_decode((string) file_get_contents($dir.'/package.json'), true);
        expect(isset($packageJson['dependencies']['@erag/lang-sync-inertia']))->toBeTrue();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles notifications alone', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['notifications'],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/NotificationController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Models/NotificationPreference.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Enums/Permission.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Models/Setting.php'))->toBeFalse();

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true);
        expect(isset($composer['require']['spatie/laravel-permission']))->toBeFalse()
            ->and(isset($composer['require']['spatie/laravel-data']))->toBeFalse();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles audit-trails ON with reporting OFF', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization', 'audit-trails'],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Models/AuditTrail.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/AuditTrails/AuditTrailController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/Reporting/AuditReportController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Queries/Reporting/AuditReportQuery.php'))->toBeFalse();

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true);
        expect(isset($composer['require']['spatie/laravel-data']))->toBeFalse();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles user-management ON with reporting OFF and audit-trails OFF', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization', 'user-management'],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/Users/UserController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Data/Users/CreateUserData.php'))->toBeTrue()
            ->and(file_exists($dir.'/tests/Feature/AuditTrails/UserAuditingTest.php'))->toBeFalse();

        $composer = json_decode((string) file_get_contents($dir.'/composer.json'), true);
        expect(isset($composer['require']['spatie/laravel-data']))->toBeTrue();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles user-management ON with audit-trails ON', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization', 'user-management', 'audit-trails'],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/Users/UserController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Models/AuditTrail.php'))->toBeTrue()
            ->and(file_exists($dir.'/tests/Feature/AuditTrails/UserAuditingTest.php'))->toBeTrue();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles user-management without audit-trails (notifications ON)', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization', 'user-management', 'notifications'],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/Users/UserController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Models/NotificationPreference.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Models/AuditTrail.php'))->toBeFalse()
            ->and(file_exists($dir.'/tests/Feature/AuditTrails/UserAuditingTest.php'))->toBeFalse();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles user-management with audit-trails and notifications', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization', 'user-management', 'audit-trails', 'notifications'],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/Users/UserController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Models/AuditTrail.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Models/NotificationPreference.php'))->toBeTrue()
            ->and(file_exists($dir.'/tests/Feature/AuditTrails/UserAuditingTest.php'))->toBeTrue();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles removing user-management leaving no broken imports, routes, or frontend references', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'registration', 'email-verification', 'password-reset', 'password-update', 'two-factor'],
        'optional_modules' => ['authorization', 'settings', 'localization', 'notifications', 'audit-trails'],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/Users/UserController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Actions/Users/CreateUserAction.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Actions/Users/UpdateUserAction.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Actions/Users/ActivateUserAction.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Actions/Users/DeactivateUserAction.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Actions/Users/DeleteUserAction.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Actions/Users/UpdateUserRolesAction.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Actions/Users/EnsureNotLastSuperAdminAction.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Data/Users/UserData.php'))->toBeFalse()
            ->and(file_exists($dir.'/resources/js/pages/users/index.tsx'))->toBeFalse()
            ->and(file_exists($dir.'/resources/js/types/users.ts'))->toBeFalse()
            ->and(file_exists($dir.'/tests/Feature/Users/UpdateUserTest.php'))->toBeFalse()
            ->and(file_exists($dir.'/tests/Feature/AuditTrails/UserAuditingTest.php'))->toBeFalse();

        $routesContent = (string) file_get_contents($dir.'/routes/web.php');
        expect($routesContent)->not->toContain('UserController as UserManagementController')
            ->and($routesContent)->not->toContain("Route::get('users'")
            ->and($routesContent)->not->toContain('users.activate');

        $sidebarContent = (string) file_get_contents($dir.'/resources/js/components/app-sidebar.tsx');
        expect($sidebarContent)->not->toContain("title: 'Users'")
            ->and($sidebarContent)->not->toContain('@/routes/users');

        $typesContent = (string) file_get_contents($dir.'/resources/js/types/index.ts');
        expect($typesContent)->not->toContain('./users');

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles auth combinations: registration OFF, email-verification ON, two-factor ON', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'email-verification', 'two-factor'],
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Actions/CreateUser.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/RegisterController.php'))->toBeFalse()
            ->and(file_exists($dir.'/tests/Feature/Controllers/RegisterControllerTest.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/LoginController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LogoutController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/UserEmailVerificationController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/UserTwoFactorAuthenticationController.php'))->toBeTrue();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles auth combinations: email-verification OFF, two-factor OFF, registration ON', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Actions/CreateUser.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/RegisterController.php'))->toBeTrue()
            ->and(file_exists($dir.'/tests/Feature/Controllers/RegisterControllerTest.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LoginController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LogoutController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/UserEmailVerificationController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/UserTwoFactorAuthenticationController.php'))->toBeFalse();

        $userModel = (string) file_get_contents($dir.'/app/Models/User.php');
        expect($userModel)->not->toContain('implements MustVerifyEmail')
            ->and($userModel)->not->toContain('use TwoFactorAuthenticatable');

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles minimal authentication: login and logout only', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout'],
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/LoginController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LogoutController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/RegisterController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/UserPasswordController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/UserEmailVerificationController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/UserTwoFactorAuthenticationController.php'))->toBeFalse();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles password recovery: login, logout, password-reset', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'password-reset'],
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/LoginController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LogoutController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/UserPasswordController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Actions/CreateUserPassword.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Actions/UpdateUserPassword.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/RegisterController.php'))->toBeFalse();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles password management: login, logout, password-update', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'password-update'],
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/LoginController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LogoutController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/UserPasswordController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Actions/UpdateUserPassword.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Actions/CreateUserPassword.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Actions/CreateUserEmailResetNotification.php'))->toBeFalse();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles 2FA: login, logout, two-factor', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'two-factor'],
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/LoginController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LogoutController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/UserTwoFactorAuthenticationController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/RegisterController.php'))->toBeFalse();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('rejects auth features missing login dependency', function (string $feature): void {
    expect(function () use ($feature): void {
        runChiselSandbox([
            'auth_features' => [$feature],
            'optional_modules' => [],
        ]);
    })->toThrow(RuntimeException::class, sprintf('The "%s" feature requires the following feature(s): login.', $feature));
})->with([
    'logout',
    'registration',
    'two-factor',
    'password-reset',
    'password-update',
    'email-verification',
]);

it('rejects invalid combinations with detailed exception', function (): void {
    expect(function (): void {
        runChiselSandbox([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['settings'], // missing authorization
        ]);
    })->toThrow(RuntimeException::class, 'The "settings" feature requires the following feature(s): authorization.');

    expect(function (): void {
        runChiselSandbox([
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['reporting'], // missing audit-trails, user-management, authorization
        ]);
    })->toThrow(RuntimeException::class, 'The "reporting" feature requires the following feature(s):');
});

it('handles cross-feature tests: audit-trails ON with settings ON vs OFF', function (): void {
    $withSettings = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization', 'settings', 'audit-trails'],
    ]);

    try {
        expect(file_exists($withSettings['dir'].'/tests/Feature/AuditTrails/SettingsAuditingTest.php'))->toBeTrue();
    } finally {
        ($withSettings['cleanup'])();
    }

    $withoutSettings = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization', 'audit-trails'],
    ]);

    try {
        expect(file_exists($withoutSettings['dir'].'/tests/Feature/AuditTrails/SettingsAuditingTest.php'))->toBeFalse();
    } finally {
        ($withoutSettings['cleanup'])();
    }
});

it('handles cross-feature tests: notifications ON with localization ON vs OFF', function (): void {
    $withLocalization = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['notifications', 'localization'],
    ]);

    try {
        expect(file_exists($withLocalization['dir'].'/tests/Feature/Notifications/NotificationLocalizationTest.php'))->toBeTrue()
            ->and(file_exists($withLocalization['dir'].'/lang/en/localization.php'))->toBeTrue();
    } finally {
        ($withLocalization['cleanup'])();
    }

    $withoutLocalization = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['notifications'],
    ]);

    try {
        expect(file_exists($withoutLocalization['dir'].'/tests/Feature/Notifications/NotificationLocalizationTest.php'))->toBeFalse()
            ->and(file_exists($withoutLocalization['dir'].'/lang/en/localization.php'))->toBeFalse()
            ->and(is_dir($withoutLocalization['dir'].'/lang/fr'))->toBeFalse()
            ->and(is_dir($withoutLocalization['dir'].'/lang/ar'))->toBeFalse()
            ->and(file_exists($withoutLocalization['dir'].'/app/Http/Controllers/NotificationController.php'))->toBeTrue();
    } finally {
        ($withoutLocalization['cleanup'])();
    }
});

it('handles cross-feature tests: reporting ON with localization ON vs OFF', function (): void {
    $withLocalization = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization', 'user-management', 'audit-trails', 'reporting', 'localization'],
    ]);

    try {
        expect(file_exists($withLocalization['dir'].'/tests/Feature/Reporting/ReportingLocalizationTest.php'))->toBeTrue();
    } finally {
        ($withLocalization['cleanup'])();
    }

    $withoutLocalization = runChiselSandbox([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization', 'user-management', 'audit-trails', 'reporting'],
    ]);

    try {
        expect(file_exists($withoutLocalization['dir'].'/tests/Feature/Reporting/ReportingLocalizationTest.php'))->toBeFalse()
            ->and(file_exists($withoutLocalization['dir'].'/app/Http/Controllers/Reporting/AuditReportController.php'))->toBeTrue();
    } finally {
        ($withoutLocalization['cleanup'])();
    }
});

it('correctly prunes unselected cross-feature tests when test-support classes are unavailable', function (): void {
    $sandbox = runChiselSandbox(
        answers: [
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization', 'audit-trails'], // settings is OFF
        ],
        beforeRun: function (string $tempDir): void {
            // Simulate missing/unavailable test-support code
            exec('rm -rf '.escapeshellarg($tempDir.'/tests/Support'));
        },
    );

    try {
        $dir = $sandbox['dir'];
        expect(file_exists($dir.'/tests/Feature/AuditTrails/SettingsAuditingTest.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Models/AuditTrail.php'))->toBeTrue();

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('tests individual feature removal from full auth configuration', function (string $removedFeature, ?string $expectedFailure): void {
    $allAuth = [
        'login',
        'logout',
        'registration',
        'email-verification',
        'password-reset',
        'password-update',
        'two-factor',
    ];

    $selection = array_values(array_filter($allAuth, fn (string $f): bool => $f !== $removedFeature));

    if ($expectedFailure !== null) {
        expect(function () use ($selection): void {
            runChiselSandbox([
                'auth_features' => $selection,
                'optional_modules' => [],
            ]);
        })->toThrow(RuntimeException::class, $expectedFailure);

        return;
    }

    $sandbox = runChiselSandbox([
        'auth_features' => $selection,
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);

        $routesContent = (string) file_get_contents($dir.'/routes/web.php');

        match ($removedFeature) {
            'logout' => expect(file_exists($dir.'/app/Http/Controllers/LogoutController.php'))->toBeFalse()
                ->and($routesContent)->not->toContain("Route::post('logout'")
                ->and($routesContent)->toContain("Route::get('login'"),

            'registration' => expect(file_exists($dir.'/app/Http/Controllers/RegisterController.php'))->toBeFalse()
                ->and(file_exists($dir.'/app/Actions/CreateUser.php'))->toBeFalse()
                ->and($routesContent)->not->toContain("Route::get('register'")
                ->and($routesContent)->toContain("Route::get('login'"),

            'email-verification' => expect(file_exists($dir.'/app/Http/Controllers/UserEmailVerificationController.php'))->toBeFalse()
                ->and(file_exists($dir.'/app/Http/Controllers/UserEmailVerificationNotificationController.php'))->toBeFalse()
                ->and($routesContent)->not->toContain("Route::get('verify-email'")
                ->and($routesContent)->not->toContain("'verified'"),

            'password-reset' => expect(file_exists($dir.'/app/Actions/CreateUserEmailResetNotification.php'))->toBeFalse()
                ->and(file_exists($dir.'/app/Actions/CreateUserPassword.php'))->toBeFalse()
                ->and(file_exists($dir.'/app/Http/Controllers/UserPasswordController.php'))->toBeTrue()
                ->and(file_exists($dir.'/app/Actions/UpdateUserPassword.php'))->toBeTrue()
                ->and($routesContent)->not->toContain("Route::get('forgot-password'")
                ->and($routesContent)->not->toContain("Route::get('reset-password/{token}'")
                ->and($routesContent)->toContain("Route::get('settings/password'"),

            'password-update' => expect(file_exists($dir.'/app/Actions/UpdateUserPassword.php'))->toBeFalse()
                ->and(file_exists($dir.'/app/Http/Controllers/UserPasswordController.php'))->toBeTrue()
                ->and(file_exists($dir.'/app/Actions/CreateUserPassword.php'))->toBeTrue()
                ->and($routesContent)->not->toContain("Route::get('settings/password'")
                ->and($routesContent)->toContain("Route::get('forgot-password'")
                ->and($routesContent)->toContain("Route::get('reset-password/{token}'"),

            'two-factor' => expect(file_exists($dir.'/app/Http/Controllers/UserTwoFactorAuthenticationController.php'))->toBeFalse()
                ->and(file_exists($dir.'/resources/js/components/two-factor-setup-modal.tsx'))->toBeFalse()
                ->and($routesContent)->not->toContain("Route::get('settings/two-factor'"),

            default => null,
        };
    } finally {
        ($sandbox['cleanup'])();
    }
})->with([
    'without login (fails because dependents require login)' => ['login', 'feature requires the following feature(s): login'],
    'without logout' => ['logout', null],
    'without registration' => ['registration', null],
    'without email-verification' => ['email-verification', null],
    'without password-reset' => ['password-reset', null],
    'without password-update' => ['password-update', null],
    'without two-factor' => ['two-factor', null],
]);

it('handles registration and email-verification: login, logout, registration, email-verification', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['login', 'logout', 'registration', 'email-verification'],
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Http/Controllers/LoginController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/LogoutController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/RegisterController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/UserEmailVerificationController.php'))->toBeTrue()
            ->and(file_exists($dir.'/app/Http/Controllers/UserPasswordController.php'))->toBeFalse()
            ->and(file_exists($dir.'/app/Http/Controllers/UserTwoFactorAuthenticationController.php'))->toBeFalse();

        $routesContent = (string) file_get_contents($dir.'/routes/web.php');
        expect($routesContent)->toContain("Route::get('login'")
            ->and($routesContent)->toContain("Route::post('logout'")
            ->and($routesContent)->toContain("Route::get('register'")
            ->and($routesContent)->toContain("Route::get('verify-email'")
            ->and($routesContent)->not->toContain("Route::get('settings/two-factor'")
            ->and($routesContent)->not->toContain("Route::get('forgot-password'");

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});
