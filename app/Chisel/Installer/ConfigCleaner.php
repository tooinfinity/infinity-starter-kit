<?php

declare(strict_types=1);

namespace App\Chisel\Installer;

/**
 * Strips installer references and configuration blocks from PHPStan NEON and PHPUnit XML configs.
 */
final class ConfigCleaner
{
    /**
     * @param  list<string>  $lines
     * @param  list<string>  $itemsToRemove
     * @return list<string>
     */
    public static function removeNeonListSectionItem(array $lines, string $sectionKey, array $itemsToRemove): array
    {
        $result = [];
        $count = count($lines);
        $i = 0;

        while ($i < $count) {
            $line = $lines[$i];

            if (preg_match('/^([ \t]*)'.preg_quote($sectionKey, '/').':[ \t]*$/', $line) === 1) {
                $headerLine = $line;
                $i++;

                $keptItems = [];
                $trailingBlanks = [];

                while ($i < $count) {
                    $subLine = $lines[$i];

                    if (preg_match('/^[ \t]*$/', $subLine) === 1) {
                        $trailingBlanks[] = $subLine;
                        $i++;

                        continue;
                    }

                    if (preg_match('/^[ \t]*-[ \t]*(.+?)[ \t]*$/', $subLine, $itemMatch) === 1) {
                        $rawValue = mb_trim($itemMatch[1], " \t'\"");
                        if (! in_array($rawValue, $itemsToRemove, true)) {
                            foreach ($trailingBlanks as $blank) {
                                $keptItems[] = $blank;
                            }

                            $trailingBlanks = [];
                            $keptItems[] = $subLine;
                        } else {
                            $trailingBlanks = [];
                        }

                        $i++;

                        continue;
                    }

                    break;
                }

                if ($keptItems !== []) {
                    $result[] = $headerLine;
                    foreach ($keptItems as $kept) {
                        $result[] = $kept;
                    }

                    foreach ($trailingBlanks as $blank) {
                        $result[] = $blank;
                    }
                }

                continue;
            }

            $result[] = $line;
            $i++;
        }

        return $result;
    }

    public static function cleanPhpstan(string $directory, bool $removePermissionMigrationExclude = false): void
    {
        $neonPath = $directory.'/phpstan.neon';
        if (! file_exists($neonPath)) {
            return;
        }

        $content = (string) file_get_contents($neonPath);
        $eol = str_contains($content, "\r\n") ? "\r\n" : "\n";
        $lines = explode($eol, $content);

        $lines = self::removeNeonListSectionItem($lines, 'bootstrapFiles', ['chisel.php', './chisel.php']);

        if ($removePermissionMigrationExclude) {
            $lines = self::removeNeonListSectionItem($lines, 'excludePaths', ['database/migrations/*_create_permission_tables.php']);
        }

        $cleaned = implode($eol, $lines);

        if ($cleaned !== $content) {
            file_put_contents($neonPath, $cleaned);
        }
    }

    public static function cleanPhpunit(string $directory): void
    {
        $phpunitPath = $directory.'/phpunit.xml';
        if (! file_exists($phpunitPath)) {
            return;
        }

        $content = (string) file_get_contents($phpunitPath);
        $cleaned = preg_replace(
            '/[ \t]*<file>app\/Console\/Commands\/InstallFeaturesCommand\.php<\/file>\r?\n?/',
            '',
            $content,
        );

        if ($cleaned !== null) {
            $cleaned = preg_replace(
                '/[ \t]*<exclude>[ \t\r\n]*<\/exclude>\r?\n?/',
                '',
                $cleaned,
            );
        }

        if ($cleaned !== null && $cleaned !== $content) {
            file_put_contents($phpunitPath, $cleaned);
        }
    }
}
