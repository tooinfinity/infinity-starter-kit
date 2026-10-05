<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use Illuminate\Support\Env;
use Illuminate\Support\Facades\Request;
use JsonException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Validates generated project integrity before and after installer cleanup.
 */
final class GeneratedApplicationValidator
{
    /**
     * Test-only failure injection point.
     *
     * When CHISEL_TEST_FAIL_STAGE is set, throws a RuntimeException at a deterministic point.
     */
    public static function injectTestFailureIfRequested(): void
    {
        $raw = Env::get(
            'CHISEL_TEST_FAIL_STAGE',
            Request::server('CHISEL_TEST_FAIL_STAGE') ?? getenv('CHISEL_TEST_FAIL_STAGE'),
        );

        $stage = is_string($raw) ? $raw : '';

        if ($stage === '') {
            return;
        }

        throw new RuntimeException(
            'CHISEL_TEST_FAIL_STAGE: deterministic test failure injected at stage ['.$stage.'].'
        );
    }

    /**
     * Validates that essential project files and valid manifest structures exist.
     *
     * @throws JsonException
     */
    public static function validatePreCleanup(string $basePath): void
    {
        $requiredFiles = [
            $basePath.'/composer.json',
            $basePath.'/package.json',
            $basePath.'/bootstrap/app.php',
            $basePath.'/routes/web.php',
            $basePath.'/app/Models/User.php',
        ];
        foreach ($requiredFiles as $file) {
            throw_unless(
                file_exists($file),
                RuntimeException::class,
                "Final validation failed: expected file [{$file}] does not exist.",
            );
        }

        $composerData = json_decode((string) file_get_contents($basePath.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        throw_unless(
            is_array($composerData) && isset($composerData['require']) && is_array($composerData['require']),
            RuntimeException::class,
            'Final validation failed: composer.json does not contain a valid require object.',
        );

        $packageData = json_decode((string) file_get_contents($basePath.'/package.json'), true, 512, JSON_THROW_ON_ERROR);
        throw_unless(
            is_array($packageData) && isset($packageData['scripts']) && is_array($packageData['scripts']),
            RuntimeException::class,
            'Final validation failed: package.json does not contain a valid scripts object.',
        );
    }

    /**
     * Thoroughly validates that all installer traces, markers, and unselected dependencies have been purged.
     *
     * @throws JsonException
     */
    public static function validatePostCleanup(InstallerContext $context, bool $cleanedUp, string $basePath): void
    {
        self::validatePreCleanup($basePath);

        $neonPath = $basePath.'/phpstan.neon';
        if (file_exists($neonPath)) {
            $neonContent = (string) file_get_contents($neonPath);
            $normalizedNeon = preg_replace_callback(
                '/^\t+/m',
                fn (array $m): string => str_repeat('    ', mb_strlen($m[0])),
                $neonContent,
            ) ?? $neonContent;

            try {
                $parsedNeon = Yaml::parse($normalizedNeon);
            } catch (Throwable $e) {
                throw new RuntimeException('Post-cleanup validation failed: phpstan.neon failed to parse: '.$e->getMessage(), 0, $e);
            }

            throw_unless(
                is_array($parsedNeon) && isset($parsedNeon['parameters']) && is_array($parsedNeon['parameters']),
                RuntimeException::class,
                'Post-cleanup validation failed: phpstan.neon does not contain valid parameters.',
            );

            if ($cleanedUp) {
                throw_if(
                    str_contains($neonContent, 'chisel.php'),
                    RuntimeException::class,
                    'Post-cleanup validation failed: phpstan.neon still references chisel.php.',
                );

                throw_if(
                    array_key_exists('bootstrapFiles', $parsedNeon['parameters']) && empty($parsedNeon['parameters']['bootstrapFiles']),
                    RuntimeException::class,
                    'Post-cleanup validation failed: phpstan.neon contains an empty bootstrapFiles section.',
                );
            }
        }

        $phpunitPath = $basePath.'/phpunit.xml';
        if (file_exists($phpunitPath)) {
            $phpunitContent = (string) file_get_contents($phpunitPath);
            $xml = @simplexml_load_string($phpunitContent);
            throw_if(
                $xml === false,
                RuntimeException::class,
                'Post-cleanup validation failed: phpunit.xml is not valid XML.',
            );

            if ($cleanedUp) {
                throw_if(
                    str_contains($phpunitContent, 'InstallFeaturesCommand'),
                    RuntimeException::class,
                    'Post-cleanup validation failed: phpunit.xml still references InstallFeaturesCommand.',
                );
            }
        }

        if (! $cleanedUp) {
            return;
        }

        $chiselConfig = is_array($context->paths['chisel'] ?? null) ? $context->paths['chisel'] : [];
        $rawChiselFiles = is_array($chiselConfig['files'] ?? null) ? $chiselConfig['files'] : [
            'app/Console/Commands/InstallFeaturesCommand.php',
            'app/Console/Commands/SetupAuthorizationCommand.php',
            'app/Console/Commands/SetupAdminUserCommand.php',
            'chisel.php',
            'chisel-paths.php',
        ];

        foreach ($rawChiselFiles as $relFile) {
            if (! is_string($relFile)) {
                continue;
            }

            throw_if(
                file_exists($basePath.'/'.$relFile),
                RuntimeException::class,
                "Post-cleanup validation failed: installer artifact [{$relFile}] was not removed.",
            );
        }

        $decodedComposer = json_decode((string) file_get_contents($basePath.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $composerData = is_array($decodedComposer) ? $decodedComposer : [];
        $composerRequire = is_array($composerData['require'] ?? null) ? $composerData['require'] : [];
        $composerRequireDev = is_array($composerData['require-dev'] ?? null) ? $composerData['require-dev'] : [];

        throw_if(
            isset($composerRequire['laravel/chisel']) || isset($composerRequireDev['laravel/chisel']),
            RuntimeException::class,
            'Post-cleanup validation failed: laravel/chisel is still present in composer.json.',
        );

        $composerJsonRaw = (string) file_get_contents($basePath.'/composer.json');
        throw_if(
            str_contains($composerJsonRaw, 'install:features'),
            RuntimeException::class,
            'Post-cleanup validation failed: composer.json still references install:features.',
        );

        if (! in_array('authorization', $context->selectedModules, true)) {
            throw_if(
                isset($composerRequire['spatie/laravel-permission']),
                RuntimeException::class,
                'Post-cleanup validation failed: spatie/laravel-permission was not removed when authorization is disabled.',
            );
        }

        if (! in_array('localization', $context->selectedModules, true)) {
            throw_if(
                isset($composerRequire['erag/laravel-lang-sync-inertia']),
                RuntimeException::class,
                'Post-cleanup validation failed: erag/laravel-lang-sync-inertia was not removed when localization is disabled.',
            );

            $decodedPackage = json_decode((string) file_get_contents($basePath.'/package.json'), true, 512, JSON_THROW_ON_ERROR);
            $packageData = is_array($decodedPackage) ? $decodedPackage : [];
            $pkgDeps = is_array($packageData['dependencies'] ?? null) ? $packageData['dependencies'] : [];
            $pkgDevDeps = is_array($packageData['devDependencies'] ?? null) ? $packageData['devDependencies'] : [];

            throw_if(
                isset($pkgDeps['@erag/lang-sync-inertia']) || isset($pkgDevDeps['@erag/lang-sync-inertia']),
                RuntimeException::class,
                'Post-cleanup validation failed: @erag/lang-sync-inertia was not removed from package.json when localization is disabled.',
            );
        }

        $staleInstallerTerms = [
            'InstallFeaturesCommand',
            'SetupAuthorizationCommand',
            'SetupAdminUserCommand',
            'chisel.php',
            'chisel-paths.php',
            'Laravel\\Chisel',
            'laravel/chisel',
            'install:features',
        ];

        $scanDirs = ['app', 'bootstrap/app.php', 'config', 'database', 'routes', 'resources', 'tests'];
        foreach ($scanDirs as $relTarget) {
            $fullTarget = $basePath.'/'.$relTarget;
            if (! file_exists($fullTarget)) {
                continue;
            }

            $filesToScan = is_file($fullTarget)
                ? [new SplFileInfo($fullTarget)]
                : new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullTarget));

            /** @var SplFileInfo $fileInfo */
            foreach ($filesToScan as $fileInfo) {
                if (! $fileInfo->isFile()) {
                    continue;
                }

                $content = (string) file_get_contents($fileInfo->getPathname());
                $relPath = str_replace($basePath.'/', '', $fileInfo->getPathname());

                throw_if(
                    str_contains($content, '@chisel-') || str_contains($content, '@end-chisel-'),
                    RuntimeException::class,
                    "Post-cleanup validation failed: unhandled chisel marker in [{$relPath}]."
                );

                foreach ($staleInstallerTerms as $term) {
                    throw_if(
                        str_contains($content, $term),
                        RuntimeException::class,
                        "Post-cleanup validation failed: stale installer reference [{$term}] found in [{$relPath}]."
                    );
                }
            }
        }
    }
}
