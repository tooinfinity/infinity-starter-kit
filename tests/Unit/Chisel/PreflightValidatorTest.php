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
    $cleanEnv('CHISEL_PREFLIGHT_DISABLE_EXTENSIONS');
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

    $origDefault = config('database.default');
    $origSqliteDb = config('database.connections.sqlite.database');

    try {
        $tempDir = sys_get_temp_dir().'/chisel_db_test_'.uniqid();
        File::ensureDirectoryExists($tempDir);

        // Test non-sqlite connection failure using an isolated unreachable connection
        config([
            'database.default' => 'unreachable_connection',
            'database.connections.unreachable_connection' => [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 49999,
                'database' => 'unreachable_db',
                'username' => 'unreachable_user',
                'password' => 'unreachable_password',
            ],
        ]);
        $context = makePreflightContext(isMockedScript: false);

        expect(fn () => PreflightValidator::validate($context, $tempDir))
            ->toThrow(RuntimeException::class, 'Preflight check failed: Database connection [unreachable_connection] could not be established');

        // Test sqlite directory validation
        config(['database.default' => 'sqlite']);
        config(['database.connections.sqlite.database' => 'database/nonexistent_dir_xyz/database.sqlite']);

        expect(fn () => PreflightValidator::validate($context, $tempDir))
            ->toThrow(RuntimeException::class, 'SQLite database directory');

        File::deleteDirectory($tempDir);
    } finally {
        $cleanEnv('CHISEL_RUN_MIGRATIONS');
        config([
            'database.default' => $origDefault,
            'database.connections.sqlite.database' => $origSqliteDb,
        ]);
    }
});

test('preflight database check throws when sqlite database file exists but is not writable', function () use ($cleanEnv): void {
    putenv('CHISEL_RUN_MIGRATIONS=true');
    $GLOBALS['_ENV']['CHISEL_RUN_MIGRATIONS'] = 'true';

    $origDefault = config('database.default');
    $origSqliteDb = config('database.connections.sqlite.database');

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
        config([
            'database.default' => $origDefault,
            'database.connections.sqlite.database' => $origSqliteDb,
        ]);
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

    $origDefault = config('database.default');
    $origSqliteDb = config('database.connections.sqlite.database');

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
        config([
            'database.default' => $origDefault,
            'database.connections.sqlite.database' => $origSqliteDb,
        ]);
    }
});

test('preflight throws when a required core php extension is missing', function () use ($cleanEnv): void {
    putenv('CHISEL_PREFLIGHT_DISABLE_EXTENSIONS=openssl');
    $GLOBALS['_ENV']['CHISEL_PREFLIGHT_DISABLE_EXTENSIONS'] = 'openssl';

    $context = makePreflightContext();

    try {
        expect(fn () => PreflightValidator::validate($context, base_path()))
            ->toThrow(RuntimeException::class, 'Missing required PHP extension [openssl]. Please install or enable the [openssl] extension in your php.ini.');
    } finally {
        $cleanEnv('CHISEL_PREFLIGHT_DISABLE_EXTENSIONS');
    }
});

test('preflight database check throws when pdo driver extension is missing', function () use ($cleanEnv): void {
    putenv('CHISEL_RUN_MIGRATIONS=true');
    $GLOBALS['_ENV']['CHISEL_RUN_MIGRATIONS'] = 'true';
    putenv('CHISEL_PREFLIGHT_DISABLE_EXTENSIONS=pdo_sqlsrv');
    $GLOBALS['_ENV']['CHISEL_PREFLIGHT_DISABLE_EXTENSIONS'] = 'pdo_sqlsrv';

    $origDefault = config('database.default');
    $origConn = config('database.connections.sqlsrv_test');

    try {
        config([
            'database.default' => 'sqlsrv_test',
            'database.connections.sqlsrv_test' => [
                'driver' => 'sqlsrv',
            ],
        ]);
        $context = makePreflightContext(isMockedScript: false);

        expect(fn () => PreflightValidator::validate($context, sys_get_temp_dir()))
            ->toThrow(RuntimeException::class, 'Missing required PHP extension [pdo_sqlsrv] for database connection [sqlsrv_test]');
    } finally {
        $cleanEnv('CHISEL_RUN_MIGRATIONS');
        $cleanEnv('CHISEL_PREFLIGHT_DISABLE_EXTENSIONS');
        config([
            'database.default' => $origDefault,
            'database.connections.sqlsrv_test' => $origConn,
        ]);
    }
});

test('preflight database check validates known database drivers', function () use ($cleanEnv): void {
    putenv('CHISEL_RUN_MIGRATIONS=true');
    $GLOBALS['_ENV']['CHISEL_RUN_MIGRATIONS'] = 'true';

    $origDefault = config('database.default');

    try {
        config([
            'database.default' => 'pgsql_test',
            'database.connections.pgsql_test' => [
                'driver' => 'pgsql',
                'host' => '127.0.0.1',
                'port' => 49998,
                'database' => 'unreachable_db',
                'username' => 'user',
                'password' => 'secret',
            ],
        ]);
        $context = makePreflightContext(isMockedScript: false);

        expect(fn () => PreflightValidator::validate($context, sys_get_temp_dir()))
            ->toThrow(RuntimeException::class, 'Preflight check failed: Database connection [pgsql_test] could not be established');
    } finally {
        $cleanEnv('CHISEL_RUN_MIGRATIONS');
        config(['database.default' => $origDefault]);
    }
});

test('preflight database check ignores unknown driver without specific extension requirement', function () use ($cleanEnv): void {
    putenv('CHISEL_RUN_MIGRATIONS=true');
    $GLOBALS['_ENV']['CHISEL_RUN_MIGRATIONS'] = 'true';

    $origDefault = config('database.default');

    try {
        config([
            'database.default' => 'custom_test',
            'database.connections.custom_test' => [
                'driver' => 'custom_driver',
                'host' => '127.0.0.1',
                'port' => 49997,
                'database' => 'custom_db',
                'username' => 'user',
                'password' => 'secret',
            ],
        ]);
        $context = makePreflightContext(isMockedScript: false);

        expect(fn () => PreflightValidator::validate($context, sys_get_temp_dir()))
            ->toThrow(RuntimeException::class, 'Preflight check failed: Database connection [custom_test] could not be established');
    } finally {
        $cleanEnv('CHISEL_RUN_MIGRATIONS');
        config(['database.default' => $origDefault]);
    }
});

test('preflight composer check validates composer executable availability', function () use ($cleanEnv): void {
    $tempDir = sys_get_temp_dir().'/chisel_composer_'.uniqid();
    File::ensureDirectoryExists($tempDir);
    File::put($tempDir.'/composer.json', json_encode(['name' => 'test/app']));

    putenv('CHISEL_RUN_COMPOSER=true');
    $GLOBALS['_ENV']['CHISEL_RUN_COMPOSER'] = 'true';

    $context = makePreflightContext(isMockedScript: false);

    try {
        if (PreflightValidator::isExecutableInPath('composer')) {
            expect(fn () => PreflightValidator::validate($context, $tempDir))->not->toThrow(RuntimeException::class);
        }

        $origPath = getenv('PATH');
        putenv('PATH=');
        try {
            expect(fn () => PreflightValidator::validate($context, $tempDir))
                ->toThrow(RuntimeException::class, 'Composer executable [composer] was not found in PATH');
        } finally {
            putenv('PATH='.$origPath);
        }

        File::delete($tempDir.'/composer.json');
        putenv('PATH=');
        try {
            expect(fn () => PreflightValidator::validate($context, $tempDir))->not->toThrow(RuntimeException::class);
        } finally {
            putenv('PATH='.$origPath);
        }
    } finally {
        $cleanEnv('CHISEL_RUN_COMPOSER');
        File::deleteDirectory($tempDir);
    }
});

test('preflight frontend check validates node runtime when package manager is not bun', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_npm_'.uniqid();
    File::ensureDirectoryExists($tempDir);
    File::put($tempDir.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));
    File::put($tempDir.'/package-lock.json', '{}');

    $context = makePreflightContext(skipNode: false);

    if (PreflightValidator::isExecutableInPath('npm') && PreflightValidator::isExecutableInPath('node')) {
        expect(fn () => PreflightValidator::validate($context, $tempDir))->not->toThrow(RuntimeException::class);
    }

    File::deleteDirectory($tempDir);
});

test('preflight frontend check throws when node is missing for non-bun package manager', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_node_missing_'.uniqid();
    File::ensureDirectoryExists($tempDir);
    File::put($tempDir.'/package.json', json_encode(['scripts' => ['build' => 'vite build']]));
    File::put($tempDir.'/package-lock.json', '{}');

    $binDir = $tempDir.'/bin';
    File::ensureDirectoryExists($binDir);
    $mockNpm = $binDir.'/npm';
    File::put($mockNpm, "#!/bin/sh\nexit 0");
    chmod($mockNpm, 0755);

    $origPath = getenv('PATH');
    putenv('PATH='.$binDir);

    $context = makePreflightContext(skipNode: false);

    try {
        expect(fn () => PreflightValidator::validate($context, $tempDir))
            ->toThrow(RuntimeException::class, 'Node.js runtime [node] is required for frontend steps but was not found in PATH');
    } finally {
        putenv('PATH='.$origPath);
        File::deleteDirectory($tempDir);
    }
});

test('preflight post scaffold tooling throws when pint is present but not executable', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_pint_'.uniqid();
    File::ensureDirectoryExists($tempDir.'/vendor/bin');
    $pintFile = $tempDir.'/vendor/bin/pint';
    File::put($pintFile, '#!/bin/sh');
    chmod($pintFile, 0644);

    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        chmod($pintFile, 0755);
        File::deleteDirectory($tempDir);
        $this->markTestSkipped('File permission checks do not apply to the root user.');
    }

    $context = makePreflightContext();

    try {
        expect(fn () => PreflightValidator::validate($context, $tempDir))
            ->toThrow(RuntimeException::class, 'Pint executable');
    } finally {
        chmod($pintFile, 0755);
        File::deleteDirectory($tempDir);
    }
});

test('preflight throws when installer files are not writable', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_unwritable_file_'.uniqid();
    File::ensureDirectoryExists($tempDir);
    $envFile = $tempDir.'/.env';
    File::put($envFile, 'APP_NAME=Laravel');
    chmod($envFile, 0444);

    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        chmod($envFile, 0666);
        File::deleteDirectory($tempDir);
        $this->markTestSkipped('File permission checks do not apply to the root user.');
    }

    $context = makePreflightContext();

    try {
        expect(fn () => PreflightValidator::validate($context, $tempDir))
            ->toThrow(RuntimeException::class, "Preflight check failed: File [{$envFile}] must be writable.");
    } finally {
        chmod($envFile, 0666);
        File::deleteDirectory($tempDir);
    }
});

test('preflight throws when installer directories are not writable', function (): void {
    $tempDir = sys_get_temp_dir().'/chisel_unwritable_dir_'.uniqid();
    File::ensureDirectoryExists($tempDir.'/storage');
    chmod($tempDir.'/storage', 0444);

    if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
        chmod($tempDir.'/storage', 0755);
        File::deleteDirectory($tempDir);
        $this->markTestSkipped('File permission checks do not apply to the root user.');
    }

    $context = makePreflightContext();

    try {
        expect(fn () => PreflightValidator::validate($context, $tempDir))
            ->toThrow(RuntimeException::class, "Preflight check failed: Directory [{$tempDir}/storage] must be writable.");
    } finally {
        chmod($tempDir.'/storage', 0755);
        File::deleteDirectory($tempDir);
    }
});
