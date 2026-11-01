<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use Illuminate\Support\Facades\DB;
use Laravel\Chisel\Chisel;
use Laravel\Chisel\Node\PackageManager;
use RuntimeException;
use Throwable;

/**
 * Validates external prerequisites (PHP, database, frontend toolchain, filesystem) before destructive mutations.
 */
final class PreflightValidator
{
    /**
     * @throws RuntimeException
     */
    public static function validate(InstallerContext $context, string $basePath): void
    {
        // 1. Dependency validation (throws if feature dependencies are unmet)
        DependencyValidator::validate($context->answers);

        // 2. Failure injection point for testing preflight failures
        GeneratedApplicationValidator::injectTestFailureIfRequested('preflight');

        // 3. PHP runtime requirements
        self::validatePhpEnvironment();

        // 4. Writable paths requirements
        self::validateWritablePaths($basePath);

        // 5. Database accessibility (only if migrations will be executed)
        self::validateDatabasePrerequisites($context, $basePath);

        // 6. Composer tooling (only if dependencies will be manipulated)
        self::validateComposerTooling($context, $basePath);

        // 7. Frontend toolchain (only if node steps are not skipped)
        self::validateFrontendToolchain($context, $basePath);

        // 8. Post-scaffold tooling executables
        self::validatePostScaffoldTooling($basePath);
    }

    public static function isExecutableInPath(string $binary): bool
    {
        if (str_contains($binary, '/') && file_exists($binary) && is_executable($binary)) {
            return true;
        }

        $pathEnv = getenv('PATH');
        if (! is_string($pathEnv) || $pathEnv === '') {
            return false;
        }

        $dirs = explode(PATH_SEPARATOR, $pathEnv);
        foreach ($dirs as $dir) {
            $path = mb_rtrim($dir, '/\\').DIRECTORY_SEPARATOR.$binary;
            if (is_file($path) && is_executable($path)) {
                return true;
            }
        }

        return false;
    }

    private static function validatePhpEnvironment(): void
    {
        throw_if(
            version_compare(phpversion(), '8.5.0', '<'),
            RuntimeException::class,
            sprintf('Preflight check failed: PHP 8.5.0 or higher is required (currently using PHP %s). Please upgrade your PHP runtime.', PHP_VERSION),
        );

        $requiredExtensions = ['pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'filter', 'openssl'];
        foreach ($requiredExtensions as $extension) {
            throw_unless(
                extension_loaded($extension),
                RuntimeException::class,
                "Preflight check failed: Missing required PHP extension [{$extension}]. Please install or enable the [{$extension}] extension in your php.ini.",
            );
        }
    }

    private static function validateWritablePaths(string $basePath): void
    {
        $directories = [
            $basePath,
            $basePath.'/storage',
            $basePath.'/bootstrap/cache',
            $basePath.'/database',
        ];

        foreach ($directories as $dir) {
            throw_if(
                is_dir($dir) && ! is_writable($dir),
                RuntimeException::class,
                "Preflight check failed: Directory [{$dir}] must be writable. Please check directory permissions or ensure write access is granted.",
            );
        }

        $files = [
            $basePath.'/.env',
            $basePath.'/composer.json',
        ];

        foreach ($files as $file) {
            throw_if(
                file_exists($file) && ! is_writable($file),
                RuntimeException::class,
                "Preflight check failed: File [{$file}] must be writable. Please check file permissions or grant write access.",
            );
        }
    }

    private static function validateDatabasePrerequisites(InstallerContext $context, string $basePath): void
    {
        $runsMigrations = ! $context->isMockedScript && ! app()->runningUnitTests() || InstallerContextResolver::installerFlag('CHISEL_RUN_MIGRATIONS');

        if (! $runsMigrations) {
            return;
        }

        $defaultConnection = config('database.default', 'sqlite');
        $connection = is_string($defaultConnection) ? $defaultConnection : 'sqlite';

        $driver = config("database.connections.{$connection}.driver", $connection);
        $requiredExtension = match ($driver) {
            'sqlite' => 'pdo_sqlite',
            'mysql', 'mariadb' => 'pdo_mysql',
            'pgsql', 'postgres' => 'pdo_pgsql',
            'sqlsrv' => 'pdo_sqlsrv',
            default => null,
        };

        if ($requiredExtension !== null && ! extension_loaded($requiredExtension)) {
            throw new RuntimeException(sprintf(
                'Preflight check failed: Missing required PHP extension [%s] for database connection [%s]. Please install or enable the [%s] extension in your php.ini.',
                $requiredExtension,
                $connection,
                $requiredExtension,
            ));
        }

        if ($connection === 'sqlite') {
            $sqlitePath = config('database.connections.sqlite.database');
            if (is_string($sqlitePath) && $sqlitePath !== ':memory:') {
                if (! str_starts_with($sqlitePath, '/')) {
                    $sqlitePath = $basePath.'/'.$sqlitePath;
                }

                $sqliteDir = dirname($sqlitePath);
                throw_if(
                    ! is_dir($sqliteDir) || ! is_writable($sqliteDir),
                    RuntimeException::class,
                    "Preflight check failed: SQLite database directory [{$sqliteDir}] is not writable. Please check directory permissions or ensure the path is writable.",
                );
                throw_if(
                    file_exists($sqlitePath) && ! is_writable($sqlitePath),
                    RuntimeException::class,
                    "Preflight check failed: SQLite database file [{$sqlitePath}] is not writable. Please check file permissions or grant write access.",
                );
            }

            return;
        }

        try {
            DB::connection($connection)->getPdo();
        } catch (Throwable $throwable) {
            throw new RuntimeException('Preflight check failed: Database connection ['.$connection.'] could not be established: '
            .$context->redact($throwable->getMessage()).'. Please verify your database configuration and credentials in .env.', (int) $throwable->getCode(), $throwable);
        }
    }

    private static function validateComposerTooling(InstallerContext $context, string $basePath): void
    {
        $runsComposer = ! $context->isMockedScript && ! app()->runningUnitTests() || InstallerContextResolver::installerFlag('CHISEL_RUN_COMPOSER');

        if (! $runsComposer) {
            return;
        }

        if (! file_exists($basePath.'/composer.json')) {
            return;
        }

        throw_unless(
            self::isExecutableInPath('composer'),
            RuntimeException::class,
            'Preflight check failed: Composer executable [composer] was not found in PATH. Please install Composer or ensure it is available in your PATH.',
        );
    }

    private static function validateFrontendToolchain(InstallerContext $context, string $basePath): void
    {
        if ($context->skipNode) {
            return;
        }

        if (! file_exists($basePath.'/package.json')) {
            return;
        }

        $npm = Chisel::in($basePath)->npm();
        $packageManager = $npm->packageManager();

        throw_unless(
            self::isExecutableInPath($packageManager->value),
            RuntimeException::class,
            sprintf(
                'Preflight check failed: Frontend package manager [%s] was detected from lockfile but is not found in PATH. Please install [%s] or run with LARAVEL_INSTALLER_NO_NODE=true.',
                $packageManager->value,
                $packageManager->value,
            ),
        );

        if ($packageManager !== PackageManager::BUN) {
            throw_unless(
                self::isExecutableInPath('node'),
                RuntimeException::class,
                'Preflight check failed: Node.js runtime [node] is required for frontend steps but was not found in PATH. Please install Node.js or run with LARAVEL_INSTALLER_NO_NODE=true.',
            );
        }
    }

    private static function validatePostScaffoldTooling(string $basePath): void
    {
        $pintPath = $basePath.'/vendor/bin/pint';
        throw_if(
            file_exists($pintPath) && ! is_executable($pintPath),
            RuntimeException::class,
            "Preflight check failed: Pint executable [{$pintPath}] is not executable. Please grant execute permissions (chmod +x {$pintPath}).",
        );

        throw_if(
            ! is_executable(PHP_BINARY),
            RuntimeException::class,
            sprintf('Preflight check failed: PHP binary [%s] is not executable. Please verify your PHP installation.', PHP_BINARY),
        );
    }
}
