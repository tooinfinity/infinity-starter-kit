<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

use App\Chisel\FeatureRegistry;
use Laravel\Chisel\Chisel;

/**
 * Removes Chisel installer machinery and synchronizes configuration files after successful installation.
 */
final class Cleanup
{
    /**
     * @param  array{
     *     chisel?: array{
     *         files?: list<string>,
     *         empty_dirs?: list<string>,
     *     },
     * }  $paths
     */
    public static function clean(string $directory, array $paths): void
    {
        // Preload installer classes before their files are removed from disk
        class_exists(ConfigCleaner::class);
        class_exists(ComposerManifestPruner::class);
        class_exists(ComposerLockSynchronizer::class);
        class_exists(DirectoryPruner::class);

        ConfigCleaner::cleanPhpunit($directory);
        ConfigCleaner::cleanPhpstan($directory);
        ComposerManifestPruner::cleanPostCreate($directory);
        ComposerLockSynchronizer::sync($directory, $paths);

        $chisel = Chisel::in($directory);
        $chiselFiles = $paths['chisel']['files'] ?? FeatureRegistry::cleanupFiles();

        $chisel->files(...$chiselFiles)->delete();

        DirectoryPruner::prune($directory, $paths['chisel']['empty_dirs'] ?? FeatureRegistry::cleanupDirectories());
    }
}
