<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Chisel\Installer\Profile;
use App\Http\Middleware\HandleInertiaRequests;
use FilesystemIterator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Focused validation pipeline for testing real generated applications against representative profiles.
 */
final class GeneratedApplicationSmokePipeline
{
    /**
     * Executes the complete smoke validation pipeline against a generated project.
     */
    public static function run(string $projectDir, Profile $profile): void
    {
        self::validateApplicationBoot($projectDir, $profile);
        self::validateDatabase($projectDir, $profile);
        self::validateRoutesAndConfig($projectDir, $profile);
        self::validateFrontend($projectDir, $profile);
        self::validateTypeScript($projectDir, $profile);
        self::validateHttpRequests($projectDir, $profile);
        self::validateFeatureAbsence($projectDir, $profile);
    }

    public static function validateApplicationBoot(string $projectDir, Profile $profile): void
    {
        $aboutProcess = GeneratedProjectFixture::run($projectDir, [PHP_BINARY, 'artisan', 'about']);
        expect($aboutProcess->getExitCode())->toBe(
            0,
            "Application failed to boot (`artisan about`) for profile [{$profile->name}]:\n".$aboutProcess->getOutput()."\n".$aboutProcess->getErrorOutput()
        );

        expect($aboutProcess->getOutput())->toContain('Laravel');
    }

    public static function validateDatabase(string $projectDir, Profile $profile): void
    {
        $migrateStatusProcess = GeneratedProjectFixture::run($projectDir, [PHP_BINARY, 'artisan', 'migrate:status']);
        expect($migrateStatusProcess->getExitCode())->toBe(
            0,
            "Database migration status check failed for profile [{$profile->name}]:\n".$migrateStatusProcess->getOutput()."\n".$migrateStatusProcess->getErrorOutput()
        );
        expect($migrateStatusProcess->getOutput())->not->toContain('Pending');

        $schemaCheckScript = sprintf(
            'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(%s::class)->bootstrap(); '
            .'$presentTables = %s; '
            .'$absentTables = %s; '
            .'$presentColumns = %s; '
            .'$absentColumns = %s; '
            .'$errors = []; '
            .'foreach ($presentTables as $table) { if (! %s::hasTable($table)) { $errors[] = "Missing expected table: {$table}"; } } '
            .'foreach ($absentTables as $table) { if (%s::hasTable($table)) { $errors[] = "Unexpected table present: {$table}"; } } '
            .'foreach ($presentColumns as $table => $columns) { foreach ($columns as $column) { if (! %s::hasColumn($table, $column)) { $errors[] = "Missing expected column {$column} on {$table}"; } } } '
            .'foreach ($absentColumns as $table => $columns) { foreach ($columns as $column) { if (%s::hasColumn($table, $column)) { $errors[] = "Unexpected column {$column} present on {$table}"; } } } '
            .'echo json_encode(["ok" => empty($errors), "errors" => $errors]);',
            Kernel::class,
            var_export($profile->expectedPresentTables(), true),
            var_export($profile->expectedAbsentTables(), true),
            var_export($profile->expectedPresentColumns(), true),
            var_export($profile->expectedAbsentColumns(), true),
            Schema::class,
            Schema::class,
            Schema::class,
            Schema::class,
        );

        $schemaProcess = GeneratedProjectFixture::run($projectDir, [PHP_BINARY, '-r', $schemaCheckScript]);
        expect($schemaProcess->getExitCode())->toBe(
            0,
            "Schema verification script failed for profile [{$profile->name}]:\n".$schemaProcess->getErrorOutput()
        );

        /** @var array{ok: bool, errors: list<string>}|null $schemaResult */
        $schemaResult = json_decode($schemaProcess->getOutput(), true);
        expect($schemaResult)->toBeArray()
            ->and($schemaResult['ok'])->toBeTrue(
                "Database schema mismatch for profile [{$profile->name}]: ".implode(', ', $schemaResult['errors'] ?? [])
            );
    }

    public static function validateRoutesAndConfig(string $projectDir, Profile $profile): void
    {
        $routeListProcess = GeneratedProjectFixture::run($projectDir, [PHP_BINARY, 'artisan', 'route:list']);
        expect($routeListProcess->getExitCode())->toBe(
            0,
            "Route list check failed for profile [{$profile->name}]:\n".$routeListProcess->getOutput()."\n".$routeListProcess->getErrorOutput()
        );

        $configProcess = GeneratedProjectFixture::run($projectDir, [PHP_BINARY, 'artisan', 'config:show', 'app.name']);
        expect($configProcess->getExitCode())->toBe(
            0,
            "Config check failed for profile [{$profile->name}]:\n".$configProcess->getOutput()."\n".$configProcess->getErrorOutput()
        );
    }

    public static function validateFrontend(string $projectDir, Profile $profile): void
    {
        expect(file_exists($projectDir.'/public/build/manifest.json'))->toBeTrue(
            "Vite manifest missing in generated project for profile [{$profile->name}]."
        );

        $buildProcess = GeneratedProjectFixture::run($projectDir, ['bun', 'run', 'build']);
        expect($buildProcess->getExitCode())->toBe(
            0,
            "Frontend build (`bun run build`) failed for profile [{$profile->name}]:\n".$buildProcess->getOutput()."\n".$buildProcess->getErrorOutput()
        );
    }

    public static function validateTypeScript(string $projectDir, Profile $profile): void
    {
        $tsProcess = GeneratedProjectFixture::run($projectDir, ['bun', 'run', 'test:types']);
        expect($tsProcess->getExitCode())->toBe(
            0,
            "TypeScript validation (`bun run test:types`) failed for profile [{$profile->name}]:\n".$tsProcess->getOutput()."\n".$tsProcess->getErrorOutput()
        );
    }

    public static function validateHttpRequests(string $projectDir, Profile $profile): void
    {
        $httpCheckScript = sprintf(
            'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; '
            .'$kernel = $app->make(%s::class); $kernel->bootstrap(); '
            .'$results = []; '
            .'$request = %s::create("/", "GET"); '
            .'$response = $app->handle($request); '
            .'$results["/"] = ["status" => $response->getStatusCode(), "has_app" => str_contains((string) $response->getContent(), "app")]; '
            .('$inertiaMiddleware = $app->make('.HandleInertiaRequests::class.'::class); ')
            .'$version = $inertiaMiddleware->version($request); '
            .'$server = ["HTTP_X_INERTIA" => "true"]; '
            .'if ($version !== null) { $server["HTTP_X_INERTIA_VERSION"] = $version; } '
            .'$loginRequest = %s::create("/login", "GET", [], [], [], $server); '
            .'$loginResponse = $app->handle($loginRequest); '
            .'$results["/login"] = ["status" => $loginResponse->getStatusCode(), "inertia" => $loginResponse->headers->get("X-Inertia") === "true"]; '
            .'echo json_encode($results);',
            Kernel::class,
            Request::class,
            Request::class,
        );

        $httpProcess = GeneratedProjectFixture::run($projectDir, [PHP_BINARY, '-r', $httpCheckScript]);
        expect($httpProcess->getExitCode())->toBe(
            0,
            "HTTP request execution failed for profile [{$profile->name}]:\n".$httpProcess->getErrorOutput()
        );

        /** @var array<string, array{status: int, has_app?: bool, inertia?: bool}>|null $httpResults */
        $httpResults = json_decode($httpProcess->getOutput(), true);
        expect($httpResults)->toBeArray()
            ->and($httpResults['/']['status'] ?? null)->toBe(200, "GET / did not return HTTP 200 for profile [{$profile->name}].")
            ->and($httpResults['/login']['status'] ?? null)->toBe(200, "GET /login did not return HTTP 200 for profile [{$profile->name}].")
            ->and($httpResults['/login']['inertia'] ?? null)->toBeTrue("GET /login did not return X-Inertia header for profile [{$profile->name}].");
    }

    public static function validateFeatureAbsence(string $projectDir, Profile $profile): void
    {
        foreach ($profile->expectedAbsentPaths() as $path) {
            expect(file_exists($projectDir.'/'.$path))->toBeFalse(
                "Expected feature path [{$path}] to be absent for profile [{$profile->name}]."
            );
        }

        foreach ($profile->expectedPresentPaths() as $path) {
            expect(file_exists($projectDir.'/'.$path))->toBeTrue(
                "Expected feature path [{$path}] to be present for profile [{$profile->name}]."
            );
        }

        $forbiddenTokens = array_merge(
            $profile->expectedForbiddenTokens(),
            [
                'InstallFeaturesCommand',
                'SetupAdminUserCommand',
                'chisel.php',
                'chisel-paths.php',
                'Laravel\\Chisel',
                'laravel/chisel',
                '@chisel-',
                '@end-chisel-',
            ]
        );

        $scanDirs = ['app', 'bootstrap', 'config', 'database', 'lang', 'resources', 'routes'];
        foreach ($scanDirs as $dir) {
            $scanPath = $projectDir.'/'.$dir;
            if (! is_dir($scanPath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($scanPath, FilesystemIterator::SKIP_DOTS)
            );

            /** @var SplFileInfo $file */
            foreach ($iterator as $file) {
                if (! $file->isFile()) {
                    continue;
                }

                $content = (string) file_get_contents($file->getPathname());
                $relativePath = str_replace($projectDir.'/', '', $file->getPathname());

                foreach ($forbiddenTokens as $token) {
                    expect($content)->not->toContain(
                        $token,
                        "Found forbidden token [{$token}] in [{$relativePath}] for profile [{$profile->name}]."
                    );
                }
            }
        }

        GeneratedProjectFixture::assertCleanOfInstallerArtifacts($projectDir, $profile->hasAuthorization());
    }
}
