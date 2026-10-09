<?php

declare(strict_types=1);

namespace Tests\Unit\Chisel;

use App\Chisel\Installer\GeneratedApplicationValidator;
use App\Chisel\Installer\InstallerContext;
use Illuminate\Support\Facades\File;
use RuntimeException;

function makeTestProject(string $path, array $overrides = []): void
{
    File::ensureDirectoryExists($path.'/bootstrap');
    File::ensureDirectoryExists($path.'/routes');
    File::ensureDirectoryExists($path.'/app/Models');

    File::put($path.'/bootstrap/app.php', '<?php return true;');
    File::put($path.'/routes/web.php', '<?php return true;');
    File::put($path.'/app/Models/User.php', '<?php namespace App\\Models; class User {}');

    $composer = array_merge(['require' => ['php' => '^8.2']], $overrides['composer'] ?? []);
    File::put($path.'/composer.json', json_encode($composer, JSON_PRETTY_PRINT));

    $package = array_merge(['scripts' => ['dev' => 'vite']], $overrides['package'] ?? []);
    File::put($path.'/package.json', json_encode($package, JSON_PRETTY_PRINT));
}

function makeContext(
    array $paths = [],
    array $selectedModules = ['authorization'],
    array $selectedAuthFeatures = ['registration'],
): InstallerContext {
    return new InstallerContext(
        providedAnswers: [],
        answers: [],
        paths: $paths,
        selectedModules: $selectedModules,
        selectedAuthFeatures: $selectedAuthFeatures,
        hasAuthorization: true,
        adminName: null,
        adminEmail: null,
        adminPassword: null,
        isNonInteractive: true,
        skipNode: true,
        isMockedScript: true,
    );
}

afterEach(function (): void {
    putenv('CHISEL_TEST_FAIL_STAGE');
    unset($_SERVER['CHISEL_TEST_FAIL_STAGE']);
});

test('injectTestFailureIfRequested throws when environment variable is present and does nothing when empty', function (): void {
    putenv('CHISEL_TEST_FAIL_STAGE=');
    GeneratedApplicationValidator::injectTestFailureIfRequested();

    putenv('CHISEL_TEST_FAIL_STAGE=cleanup');
    expect(fn () => GeneratedApplicationValidator::injectTestFailureIfRequested())
        ->toThrow(RuntimeException::class, 'CHISEL_TEST_FAIL_STAGE: deterministic test failure injected at stage [cleanup].');
});

test('validatePreCleanup validates required files, composer require, and package scripts', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_val_pre_'.uniqid();
    File::ensureDirectoryExists($tempDir);

    try {
        expect(fn () => GeneratedApplicationValidator::validatePreCleanup($tempDir))
            ->toThrow(RuntimeException::class, 'Final validation failed: expected file');

        makeTestProject($tempDir);
        // Valid project passes
        GeneratedApplicationValidator::validatePreCleanup($tempDir);

        // Invalid composer require
        File::put($tempDir.'/composer.json', json_encode(['name' => 'test']));
        expect(fn () => GeneratedApplicationValidator::validatePreCleanup($tempDir))
            ->toThrow(RuntimeException::class, 'composer.json does not contain a valid require object');

        // Restore composer, corrupt package scripts
        File::put($tempDir.'/composer.json', json_encode(['require' => ['php' => '^8.2']]));
        File::put($tempDir.'/package.json', json_encode(['name' => 'test']));
        expect(fn () => GeneratedApplicationValidator::validatePreCleanup($tempDir))
            ->toThrow(RuntimeException::class, 'package.json does not contain a valid scripts object');
    } finally {
        File::deleteDirectory($tempDir);
    }
});

test('validatePostCleanup returns early when cleanedUp is false', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_val_post_false_'.uniqid();
    makeTestProject($tempDir);

    try {
        $context = makeContext();
        // Should succeed without running post-cleanup checks
        GeneratedApplicationValidator::validatePostCleanup($context, false, $tempDir);
        expect(true)->toBeTrue();
    } finally {
        File::deleteDirectory($tempDir);
    }
});

test('validatePostCleanup catches unparseable phpstan.neon YAML and handles invalid parameters', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_val_neon_'.uniqid();
    makeTestProject($tempDir);

    try {
        $context = makeContext();

        // 1. Unparseable YAML triggering line 102..103
        File::put($tempDir.'/phpstan.neon', "parameters:\n  invalid: [unclosed bracket");
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'Post-cleanup validation failed: phpstan.neon failed to parse:');

        // 2. phpstan.neon without valid parameters
        File::put($tempDir.'/phpstan.neon', "not_parameters: 123\n");
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'phpstan.neon does not contain valid parameters');

        // 3. phpstan.neon still referencing chisel.php when cleaned up
        File::put($tempDir.'/phpstan.neon', "parameters:\n  bootstrapFiles:\n    - chisel.php\n");
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'phpstan.neon still references chisel.php');

        // 4. phpstan.neon with empty bootstrapFiles section
        File::put($tempDir.'/phpstan.neon', "parameters:\n  bootstrapFiles: []\n");
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'phpstan.neon contains an empty bootstrapFiles section');
    } finally {
        File::deleteDirectory($tempDir);
    }
});

test('validatePostCleanup validates phpunit.xml XML parsing and stale InstallFeaturesCommand reference', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_val_xml_'.uniqid();
    makeTestProject($tempDir);

    try {
        $context = makeContext();

        // Invalid XML
        File::put($tempDir.'/phpunit.xml', '<phpunit><unclosed>');
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'phpunit.xml is not valid XML');

        // Stale InstallFeaturesCommand
        File::put($tempDir.'/phpunit.xml', '<phpunit><exclude>InstallFeaturesCommand</exclude></phpunit>');
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'phpunit.xml still references InstallFeaturesCommand');
    } finally {
        File::deleteDirectory($tempDir);
    }
});

test('validatePostCleanup skips non-string entries in chisel files and detects remaining installer artifacts', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_val_files_'.uniqid();
    makeTestProject($tempDir);

    try {
        // Paths containing non-string items (triggers line 161 continue)
        $context = makeContext(paths: [
            'chisel' => [
                'files' => [
                    123,
                    null,
                    false,
                    'leftover_installer_file.php',
                ],
            ],
        ]);

        // File does not exist yet -> should pass chisel file check
        // Create unremoved artifact
        File::put($tempDir.'/leftover_installer_file.php', '<?php');
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'installer artifact [leftover_installer_file.php] was not removed.');

        // Remove leftover file
        File::delete($tempDir.'/leftover_installer_file.php');
        GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir);
    } finally {
        File::deleteDirectory($tempDir);
    }
});

test('validatePostCleanup checks laravel/chisel presence and install:features script in composer.json', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_val_chiselpkg_'.uniqid();
    makeTestProject($tempDir);

    try {
        $context = makeContext();

        // laravel/chisel in require
        File::put($tempDir.'/composer.json', json_encode([
            'require' => ['php' => '^8.2', 'laravel/chisel' => '^1.0'],
        ]));
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'laravel/chisel is still present in composer.json.');

        // install:features in composer.json
        File::put($tempDir.'/composer.json', json_encode([
            'require' => ['php' => '^8.2'],
            'scripts' => ['post-create-project-cmd' => '@php artisan install:features'],
        ]));
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'composer.json still references install:features.');
    } finally {
        File::deleteDirectory($tempDir);
    }
});

test('validatePostCleanup checks spatie/laravel-permission removal when authorization is disabled', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_val_authpkg_'.uniqid();
    makeTestProject($tempDir);

    try {
        $context = makeContext(selectedModules: []); // no authorization

        // spatie/laravel-permission still present
        File::put($tempDir.'/composer.json', json_encode([
            'require' => ['php' => '^8.2', 'spatie/laravel-permission' => '^6.0'],
        ]));
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'spatie/laravel-permission was not removed when authorization is disabled.');

        // Cleaned up passes
        File::put($tempDir.'/composer.json', json_encode([
            'require' => ['php' => '^8.2'],
        ]));
        GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir);
        expect(true)->toBeTrue();
    } finally {
        File::deleteDirectory($tempDir);
    }
});

test('validatePostCleanup checks localization package removal when localization is disabled', function (): void {

    $tempDir = sys_get_temp_dir().'/chisel_val_locale_'.uniqid();
    makeTestProject($tempDir);

    try {
        $context = makeContext(selectedModules: ['authorization']); // no localization

        // Composer still has erag/laravel-lang-sync-inertia
        File::put($tempDir.'/composer.json', json_encode([
            'require' => ['php' => '^8.2', 'erag/laravel-lang-sync-inertia' => '^1.0'],
        ]));
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'erag/laravel-lang-sync-inertia was not removed when localization is disabled');

        // Fix composer, break package.json
        File::put($tempDir.'/composer.json', json_encode([
            'require' => ['php' => '^8.2'],
        ]));
        File::put($tempDir.'/package.json', json_encode([
            'scripts' => ['dev' => 'vite'],
            'dependencies' => ['@erag/lang-sync-inertia' => '^1.0'],
        ]));
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, '@erag/lang-sync-inertia was not removed from package.json when localization is disabled');
    } finally {
        File::deleteDirectory($tempDir);
    }
});

test('validatePostCleanup detects unhandled chisel markers and stale installer terms', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_val_markers_'.uniqid();
    makeTestProject($tempDir);

    try {
        $context = makeContext(paths: ['chisel' => ['files' => []]]);

        // Unhandled chisel marker
        File::put($tempDir.'/app/marked_file.php', '<?php // @chisel-auth');
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'unhandled chisel marker in [app/marked_file.php].');

        // Stale installer term
        File::put($tempDir.'/app/marked_file.php', '<?php // install:features');
        expect(fn () => GeneratedApplicationValidator::validatePostCleanup($context, true, $tempDir))
            ->toThrow(RuntimeException::class, 'stale installer reference [install:features] found in [app/marked_file.php].');
    } finally {
        File::deleteDirectory($tempDir);
    }
});
