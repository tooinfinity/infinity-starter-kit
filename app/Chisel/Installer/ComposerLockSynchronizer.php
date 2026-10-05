<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use App\Chisel\FeatureRegistry;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Synchronizes composer.lock by detecting removed candidate packages and invoking Composer.
 */
final class ComposerLockSynchronizer
{
    /**
     * @param  array<string, mixed>|null  $paths
     */
    public static function sync(string $directory, ?array $paths = null): void
    {
        $composerJsonPath = $directory.'/composer.json';
        $composerLockPath = $directory.'/composer.lock';

        if (! file_exists($composerJsonPath) || ! file_exists($composerLockPath)) {
            return;
        }

        /** @var array{require?: array<string, string>, require-dev?: array<string, string>} $composerData */
        $composerData = json_decode((string) file_get_contents($composerJsonPath), true, 512, JSON_THROW_ON_ERROR);
        /** @var array{packages?: list<array{name?: string}>, packages-dev?: list<array{name?: string}>} $lockData */
        $lockData = json_decode((string) file_get_contents($composerLockPath), true, 512, JSON_THROW_ON_ERROR);

        $lockedPackages = [];
        foreach (array_merge($lockData['packages'] ?? [], $lockData['packages-dev'] ?? []) as $pkg) {
            if (isset($pkg['name'])) {
                $lockedPackages[$pkg['name']] = true;
            }
        }

        if ($paths === null) {
            $pathsFile = $directory.'/chisel-paths.php';
            $paths = file_exists($pathsFile) ? (require $pathsFile) : FeatureRegistry::toPathsArray();
        }

        $candidates = ['laravel/chisel'];
        if (is_array($paths)) {
            foreach ($paths as $data) {
                if (! is_array($data)) {
                    continue;
                }

                if (isset($data['composer_package']) && is_string($data['composer_package'])) {
                    $candidates[] = $data['composer_package'];
                }

                if (isset($data['composer_packages']) && is_array($data['composer_packages'])) {
                    foreach ($data['composer_packages'] as $pkg) {
                        if (is_string($pkg)) {
                            $candidates[] = $pkg;
                        }
                    }
                }
            }
        }

        $candidates = array_values(array_unique($candidates));

        $removedPackages = [];
        foreach ($candidates as $candidate) {
            $inJson = isset($composerData['require'][$candidate]) || isset($composerData['require-dev'][$candidate]);
            if (! $inJson && isset($lockedPackages[$candidate])) {
                $removedPackages[] = $candidate;
            }
        }

        if ($removedPackages === []) {
            return;
        }

        $vendorPath = $directory.'/vendor';
        $hasRealVendor = is_dir($vendorPath) && ! is_link($vendorPath);

        $command = [
            'composer',
            'update',
            ...$removedPackages,
            '--with-all-dependencies',
            '--no-scripts',
            '--no-audit',
            '--no-security-blocking',
            '--no-interaction',
        ];

        if (! $hasRealVendor) {
            $command[] = '--no-install';
            $command[] = '--no-autoloader';
        }

        $process = new Process($command, $directory);
        $process->setTimeout(null);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'Failed to synchronize composer.lock: %s',
                mb_trim($process->getErrorOutput() !== '' ? $process->getErrorOutput() : $process->getOutput()),
            ));
        }

        if ($hasRealVendor) {
            @unlink($directory.'/bootstrap/cache/packages.php');
            @unlink($directory.'/bootstrap/cache/services.php');

            if (file_exists($directory.'/artisan')) {
                $discover = new Process([PHP_BINARY, 'artisan', 'package:discover', '--ansi'], $directory);
                $discover->run();
            }
        }
    }
}
