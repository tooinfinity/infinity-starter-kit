<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

/**
 * Prunes maintainer AI tooling and assistant guidelines when deselected during installation.
 */
final class AiToolingPruner
{
    /**
     * @return list<string>
     */
    public static function files(): array
    {
        return [
            '.mcp.json',
            'AGENTS.md',
            'CLAUDE.md',
            'GEMINI.md',
            'boost.json',
            'opencode.json',
        ];
    }

    /**
     * @return list<string>
     */
    public static function directories(): array
    {
        return [
            '.agents',
            '.ai',
            '.amp',
            '.claude',
            '.codex',
            '.cursor',
            '.gemini',
            '.junie',
        ];
    }

    public static function prune(string $directory): void
    {
        $baseRoot = realpath($directory) ?: $directory;

        foreach (self::files() as $relFile) {
            $fullPath = $baseRoot.'/'.$relFile;
            if (is_file($fullPath) || is_link($fullPath)) {
                @chmod($fullPath, 0777);
                @unlink($fullPath);
            }
        }

        foreach (self::directories() as $relDir) {
            $fullPath = $baseRoot.'/'.$relDir;
            if (is_dir($fullPath)) {
                self::deleteDirectory($fullPath);
            }
        }
    }

    private static function deleteDirectory(string $path): void
    {
        @chmod($path, 0777);

        /** @var list<string> $items */
        $items = scandir($path) ?: [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path.'/'.$item;
            if (is_dir($itemPath) && ! is_link($itemPath)) {
                self::deleteDirectory($itemPath);
            } else {
                @chmod($itemPath, 0777);
                @unlink($itemPath);
            }
        }

        @rmdir($path);
    }
}
