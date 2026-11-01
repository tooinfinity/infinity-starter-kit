<?php

declare(strict_types=1);

namespace Tests\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Process\Process;

/**
 * Manages isolated real generated-project fixtures for installer testing.
 */
final class GeneratedProjectFixture
{
    public static function create(): string
    {
        $baseTempDir = storage_path('framework/testing');
        if (! is_dir($baseTempDir)) {
            mkdir($baseTempDir, 0755, true);
        }

        $tempDir = $baseTempDir.'/generated_project_'.uniqid();
        mkdir($tempDir, 0755, true);
        mkdir($tempDir.'/.git', 0755, true);

        $root = base_path();
        $filesOutput = shell_exec('git -C '.escapeshellarg($root).' ls-files -c -o --exclude-standard') ?: '';
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

        // Prepare SQLite database schema for real installation.
        self::run($tempDir, [PHP_BINARY, 'artisan', 'key:generate', '--no-interaction'], [], 60)->mustRun();

        return $tempDir;
    }

    public static function remove(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        new Process(['rm', '-rf', $dir])
            ->setTimeout(60)
            ->run();
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $env
     */
    public static function run(string $dir, array $command, array $env = [], int $timeout = 300): Process
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

    public static function assertCleanOfInstallerArtifacts(string $tempDir, bool $authorizationEnabled = true): void
    {
        $removedPaths = [
            'chisel.php',
            'chisel-paths.php',
            'app/Console/Commands/InstallFeaturesCommand.php',
            'app/Console/Commands/SetupAdminUserCommand.php',
            'app/Chisel',
            'tests/Support',
            'tests/Feature/Authorization/SetupAdminUserCommandTest.php',
            'tests/Feature/Chisel',
            'tests/Unit/Chisel',
        ];

        if (! $authorizationEnabled) {
            $removedPaths[] = 'app/Console/Commands/SetupAuthorizationCommand.php';
        }

        foreach ($removedPaths as $path) {
            expect(file_exists($tempDir.'/'.$path))
                ->toBeFalse("Expected installer artifact [{$path}] to be removed from generated project.");
        }

        if ($authorizationEnabled) {
            expect(file_exists($tempDir.'/app/Console/Commands/SetupAuthorizationCommand.php'))
                ->toBeTrue('Expected SetupAuthorizationCommand.php to remain in generated project when authorization is enabled.');
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
            'SetupAdminUserCommand',
            'chisel.php',
            'chisel-paths.php',
            'Laravel\\Chisel',
            'laravel/chisel',
            'install:features',
            '@chisel-',
            '@end-chisel-',
        ];

        if (! $authorizationEnabled) {
            $forbiddenTokens[] = 'SetupAuthorizationCommand';
        }

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
}
