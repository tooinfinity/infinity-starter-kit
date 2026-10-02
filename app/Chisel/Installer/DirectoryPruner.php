<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

/**
 * Prunes empty directories while strictly protecting framework root directories.
 */
final class DirectoryPruner
{
    /**
     * @param  list<string>  $paths
     */
    public static function prune(string $directory, array $paths): void
    {
        $baseRoot = realpath($directory) ?: $directory;
        $dirs = array_values(array_unique(array_filter($paths, fn (string $p): bool => $p !== '' && $p !== '.')));
        usort($dirs, fn (string $a, string $b): int => mb_substr_count($b, '/') <=> mb_substr_count($a, '/'));

        $protectedRootDirs = [
            'app',
            'bootstrap',
            'config',
            'database',
            'lang',
            'public',
            'resources',
            'routes',
            'storage',
            'tests',
        ];

        foreach ($dirs as $relPath) {
            $current = mb_trim($relPath, '/');

            while ($current !== '' && $current !== '.') {
                if (in_array($current, $protectedRootDirs, true) || ! str_contains($current, '/')) {
                    break;
                }

                $fullPath = $directory.'/'.$current;
                $realPath = realpath($fullPath);

                if ($realPath === false || ! is_dir($realPath)) {
                    $current = dirname($current);

                    continue;
                }

                if ($realPath === $baseRoot || ! str_starts_with($realPath, $baseRoot.'/')) {
                    break;
                }

                $items = scandir($realPath);
                if ($items !== false && count($items) === 2) {
                    @rmdir($realPath);
                    $current = dirname($current);
                } else {
                    break;
                }
            }
        }
    }
}
