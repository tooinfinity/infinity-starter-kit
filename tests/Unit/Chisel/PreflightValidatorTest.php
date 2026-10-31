<?php

declare(strict_types=1);

namespace Tests\Unit\Chisel;

use App\Chisel\Installer\InstallerContext;
use App\Chisel\Installer\PreflightValidator;
use Illuminate\Support\Facades\File;
use RuntimeException;

$cleanEnv = function (string $key): void {
    putenv($key);
    unset($GLOBALS['_ENV'][$key], $GLOBALS['_SERVER'][$key]);
};

function makePreflightContext(
    array $answers = [],
    bool $skipNode = true,
    bool $isMockedScript = true,
): InstallerContext {
    return new InstallerContext(
        providedAnswers: $answers,
        answers: $answers,
        paths: [],
        selectedModules: ['authorization'],
        selectedAuthFeatures: ['registration'],
        hasAuthorization: true,
        adminName: null,
        adminEmail: null,
        adminPassword: null,
        isNonInteractive: true,
        skipNode: $skipNode,
        isMockedScript: $isMockedScript,
    );
}

afterEach(function () use ($cleanEnv): void {
    $cleanEnv('CHISEL_TEST_FAIL_STAGE');
});

test('preflight passes on standard valid environment', function (): void {
    $context = makePreflightContext([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization'],
    ]);

    expect(fn () => PreflightValidator::validate($context, base_path()))->not->toThrow(RuntimeException::class);
});

test('preflight throws when dependency validation fails', function (): void {
    $context = makePreflightContext([
        'auth_features' => ['two_factor'],
        'optional_modules' => [],
    ]);

    expect(fn () => PreflightValidator::validate($context, base_path()))
        ->toThrow(RuntimeException::class);
});

test('preflight throws when test failure stage is preflight', function (): void {
    putenv('CHISEL_TEST_FAIL_STAGE=preflight');
    $context = makePreflightContext([
        'auth_features' => ['login', 'registration'],
        'optional_modules' => ['authorization'],
    ]);

    expect(fn () => PreflightValidator::validate($context, base_path()))
        ->toThrow(RuntimeException::class, 'deterministic test failure injected at stage [preflight]');
});

test('isExecutableInPath finds common system binaries and returns false for nonexistent ones', function (): void {
    expect(PreflightValidator::isExecutableInPath('php'))->toBeTrue();
    expect(PreflightValidator::isExecutableInPath('nonexistent-binary-xyz-12345'))->toBeFalse();
});

test('preflight validates frontend toolchain when node steps are not skipped', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_preflight_'.uniqid();
    File::ensureDirectoryExists($tempDir);
    File::put($tempDir.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));
    // Create bun.lockb to simulate bun project
    File::put($tempDir.'/bun.lockb', '');

    $context = makePreflightContext(
        answers: [
            'auth_features' => ['login', 'registration'],
            'optional_modules' => ['authorization'],
        ],
        skipNode: false,
    );

    // If bun is installed on the system, this succeeds. If not, it throws.
    $bunExists = PreflightValidator::isExecutableInPath('bun');
    if ($bunExists) {
        expect(fn () => PreflightValidator::validate($context, $tempDir))->not->toThrow(RuntimeException::class);
    } else {
        expect(fn () => PreflightValidator::validate($context, $tempDir))
            ->toThrow(RuntimeException::class, 'Frontend package manager [bun]');
    }

    File::deleteDirectory($tempDir);
});

test('isExecutableInPath handles absolute paths and empty PATH environment', function (): void {
    expect(PreflightValidator::isExecutableInPath(PHP_BINARY))->toBeTrue();
    expect(PreflightValidator::isExecutableInPath('/nonexistent/path/binary_xyz'))->toBeFalse();

    $origPath = getenv('PATH');
    putenv('PATH=');
    try {
        expect(PreflightValidator::isExecutableInPath('php'))->toBeFalse();
    } finally {
        putenv('PATH='.$origPath);
    }
});

test('preflight frontend check returns early when package.json does not exist', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_preflight_'.uniqid();
    File::ensureDirectoryExists($tempDir);

    $context = makePreflightContext(skipNode: false);
    expect(fn () => PreflightValidator::validate($context, $tempDir))->not->toThrow(RuntimeException::class);

    File::deleteDirectory($tempDir);
});

test('preflight database check validates sqlite directory and throws when non-sqlite connection fails', function () use ($cleanEnv): void {
    putenv('CHISEL_RUN_MIGRATIONS=true');
    $GLOBALS['_ENV']['CHISEL_RUN_MIGRATIONS'] = 'true';

    $origMysqlPort = config('database.connections.mysql.port');

    try {
        $tempDir = sys_get_temp_dir().'/chisel_db_test_'.uniqid();
        File::ensureDirectoryExists($tempDir);

        // Test non-sqlite connection failure
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.port' => 49999,
        ]);
        $context = makePreflightContext(isMockedScript: false);

        expect(fn () => PreflightValidator::validate($context, $tempDir))
            ->toThrow(RuntimeException::class, 'Preflight check failed: Database connection [mysql] could not be established');

        // Restore default sqlite
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => 'database/nonexistent_dir_xyz/database.sqlite']);

        expect(fn () => PreflightValidator::validate($context, $tempDir))
            ->toThrow(RuntimeException::class, 'SQLite database directory');

        File::deleteDirectory($tempDir);
    } finally {
        $cleanEnv('CHISEL_RUN_MIGRATIONS');
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
        if ($origMysqlPort !== null) {
            config(['database.connections.mysql.port' => $origMysqlPort]);
        }
    }
});

test('preflight database check throws when sqlite database file exists but is not writable', function () use ($cleanEnv): void {
    putenv('CHISEL_RUN_MIGRATIONS=true');
    $GLOBALS['_ENV']['CHISEL_RUN_MIGRATIONS'] = 'true';

    $tempDir = sys_get_temp_dir().'/chisel_db_unwritable_'.uniqid();
    File::ensureDirectoryExists($tempDir);
    $dbFile = $tempDir.'/database.sqlite';
    File::put($dbFile, '');
    chmod($dbFile, 0444);

    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        chmod($dbFile, 0666);
        File::deleteDirectory($tempDir);
        $cleanEnv('CHISEL_RUN_MIGRATIONS');
        $this->markTestSkipped('File permission checks do not apply to the root user.');
    }

    try {
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => $dbFile]);
        $context = makePreflightContext(isMockedScript: false);

        expect(fn () => PreflightValidator::validate($context, $tempDir))
            ->toThrow(RuntimeException::class, "Preflight check failed: SQLite database file [{$dbFile}] is not writable.");
    } finally {
        chmod($dbFile, 0666);
        File::deleteDirectory($tempDir);
        $cleanEnv('CHISEL_RUN_MIGRATIONS');
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }
});

test('preflight frontend check throws when detected package manager binary is missing in PATH', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_preflight_missing_bin_'.uniqid();
    File::ensureDirectoryExists($tempDir);
    File::put($tempDir.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));
    File::put($tempDir.'/bun.lockb', '');

    $context = makePreflightContext(skipNode: false);

    $origPath = getenv('PATH');
    putenv('PATH=');

    try {
        expect(fn () => PreflightValidator::validate($context, $tempDir))
            ->toThrow(RuntimeException::class, 'Preflight check failed: Frontend package manager [bun] was detected from lockfile but is not found in PATH');
    } finally {
        putenv('PATH='.$origPath);
        File::deleteDirectory($tempDir);
    }
});

test('preflight database check passes when sqlite database is valid and writable', function () use ($cleanEnv): void {
    putenv('CHISEL_RUN_MIGRATIONS=true');
    $GLOBALS['_ENV']['CHISEL_RUN_MIGRATIONS'] = 'true';

    $tempDir = sys_get_temp_dir().'/chisel_db_valid_'.uniqid();
    File::ensureDirectoryExists($tempDir);
    $dbFile = $tempDir.'/database.sqlite';
    File::put($dbFile, '');

    try {
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => $dbFile]);
        $context = makePreflightContext(isMockedScript: false);

        expect(fn () => PreflightValidator::validate($context, $tempDir))->not->toThrow(RuntimeException::class);
    } finally {
        File::deleteDirectory($tempDir);
        $cleanEnv('CHISEL_RUN_MIGRATIONS');
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => ':memory:']);
    }
});
