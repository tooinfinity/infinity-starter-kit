<?php

declare(strict_types=1);

/**
 * Creates an isolated sandbox of git-tracked files, executes chisel.php, and yields sandbox directory.
 *
 * @param  array<string, mixed>  $answers
 * @return array{dir: string, cleanup: Closure(): void}
 */
function runChiselSandbox(array $answers): array
{
    $tempDir = sys_get_temp_dir().'/chisel_comb_'.uniqid();
    mkdir($tempDir, 0777, true);

    $files = explode("\n", mb_trim((string) shell_exec('git ls-files')));
    foreach ($files as $file) {
        if ($file === '') {
            continue;
        }

        $target = $tempDir.'/'.$file;
        @mkdir(dirname($target), 0777, true);
        copy(base_path($file), $target);
    }

    @symlink(base_path('vendor'), $tempDir.'/vendor');

    $script = require $tempDir.'/chisel.php';
    $script->chisel($answers);

    /** @var array<string, mixed> $paths */
    $paths = require $tempDir.'/chisel-paths.php';
    chiselCleanup($tempDir, $paths);

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
 * Asserts no remaining @chisel or @end-chisel markers exist in source files.
 */
function assertNoOrphanedMarkersInSandbox(string $directory): void
{
    $rdi = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
    $filesWithMarkers = [];

    foreach ($rdi as $file) {
        if ($file->isFile() && ! str_starts_with($file->getFilename(), '.') && ! str_contains($file->getPathname(), '/tests/') && $file->getFilename() !== 'README.md') {
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
        'auth_features' => ['registration', 'email-verification', 'two-factor-authentication'],
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
            ->and(file_exists($dir.'/tests/Feature/Chisel'))->toBeFalse()
            ->and(file_exists($dir.'/tests/Unit/Chisel'))->toBeFalse();

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

        assertNoOrphanedMarkersInSandbox($dir);
        assertPhpSyntaxValid($dir);
    } finally {
        ($sandbox['cleanup'])();
    }
});

it('handles authorization alone', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['registration'],
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
        'auth_features' => ['registration'],
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
        'auth_features' => ['registration'],
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
        'auth_features' => ['registration'],
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
        'auth_features' => ['registration'],
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
        'auth_features' => ['registration'],
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

it('handles auth combinations: registration OFF, email-verification ON, two-factor ON', function (): void {
    $sandbox = runChiselSandbox([
        'auth_features' => ['email-verification', 'two-factor-authentication'],
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Actions/CreateUser.php'))->toBeFalse()
            ->and(file_exists($dir.'/tests/Feature/Controllers/RegistrationTest.php'))->toBeFalse()
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
        'auth_features' => ['registration'],
        'optional_modules' => [],
    ]);

    try {
        $dir = $sandbox['dir'];

        expect(file_exists($dir.'/app/Actions/CreateUser.php'))->toBeTrue()
            ->and(file_exists($dir.'/tests/Feature/Controllers/RegistrationTest.php'))->toBeTrue()
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

it('rejects invalid combinations with detailed exception', function (): void {
    expect(function (): void {
        runChiselSandbox([
            'auth_features' => ['registration'],
            'optional_modules' => ['settings'], // missing authorization
        ]);
    })->toThrow(RuntimeException::class, 'The "settings" module requires the following module(s): authorization.');

    expect(function (): void {
        runChiselSandbox([
            'auth_features' => ['registration'],
            'optional_modules' => ['reporting'], // missing audit-trails, user-management, authorization
        ]);
    })->toThrow(RuntimeException::class, 'The "reporting" module requires the following module(s):');
});
