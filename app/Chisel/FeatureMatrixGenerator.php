<?php

declare(strict_types=1);

namespace App\Chisel;

use RuntimeException;

/**
 * Deterministic markdown documentation generator for optional starter kit features.
 */
final readonly class FeatureMatrixGenerator
{
    public const string START_MARKER = '<!-- @chisel-feature-matrix -->';

    public const string END_MARKER = '<!-- @end-chisel-feature-matrix -->';

    /**
     * Generate the markdown feature matrix table for all optional modules.
     */
    public static function generateOptionalModulesTable(): string
    {
        return self::generateTable(FeatureRegistry::optionalModules());
    }

    /**
     * Generate a deterministic markdown matrix table for given feature definitions.
     *
     * @param  array<string, FeatureDefinition>  $features
     */
    public static function generateTable(array $features): string
    {
        $headers = [
            'Module',
            'Identifier',
            'Description',
            'Required Dependencies',
            'Optional Dependencies',
            'Composer Packages',
            'Frontend Packages (Bun)',
            'Permissions',
            'Default',
            'Capabilities',
        ];

        $alignments = array_fill(0, count($headers), ':---');

        $rows = [];
        $rows[] = '| '.implode(' | ', $headers).' |';
        $rows[] = '| '.implode(' | ', $alignments).' |';

        foreach ($features as $feature) {
            $requiredDeps = $feature->dependencies !== []
                ? implode(', ', array_map(fn (string $dep): string => "`{$dep}`", $feature->dependencies))
                : '—';

            $optionalDeps = $feature->optionalDependencies !== []
                ? implode(', ', array_map(fn (string $dep): string => "`{$dep}`", $feature->optionalDependencies))
                : '—';

            $composerPkgs = $feature->composerPackages !== []
                ? implode(', ', array_map(fn (string $pkg): string => "`{$pkg}`", $feature->composerPackages))
                : '—';

            $frontendPkgs = $feature->frontendPackages !== []
                ? implode(', ', array_map(fn (string $pkg): string => "`{$pkg}`", $feature->frontendPackages))
                : '—';

            $permissions = $feature->permissions !== []
                ? implode(', ', array_map(fn (string $perm): string => "`{$perm}`", $feature->permissions))
                : '—';

            $capabilities = $feature->capabilities !== []
                ? implode(', ', $feature->capabilities)
                : '—';

            $description = mb_trim($feature->description) !== ''
                ? mb_trim($feature->description)
                : '—';

            $defaultStatus = $feature->default ? 'Enabled' : 'Disabled';

            $rowCells = [
                "**{$feature->label}**",
                "`{$feature->key}`",
                $description,
                $requiredDeps,
                $optionalDeps,
                $composerPkgs,
                $frontendPkgs,
                $permissions,
                $defaultStatus,
                $capabilities,
            ];

            $rows[] = '| '.implode(' | ', $rowCells).' |';
        }

        return implode("\n", $rows);
    }

    /**
     * Replace the marked feature matrix section in the provided documentation content.
     */
    public static function updateContent(string $content, string $table): string
    {
        $pattern = '/('.preg_quote(self::START_MARKER, '/').')(.*?)('.preg_quote(self::END_MARKER, '/').')/s';

        if (! preg_match($pattern, $content)) {
            throw new RuntimeException(sprintf(
                'Could not find feature matrix markers [%s] and [%s] in documentation.',
                self::START_MARKER,
                self::END_MARKER,
            ));
        }

        $replacement = self::START_MARKER."\n".$table."\n".self::END_MARKER;

        return (string) preg_replace($pattern, $replacement, $content);
    }

    /**
     * Check if the marked feature matrix section in the content matches the expected table.
     */
    public static function isContentFresh(string $content, string $table): bool
    {
        $pattern = '/'.preg_quote(self::START_MARKER, '/').'(.*?)'.preg_quote(self::END_MARKER, '/').'/s';

        if (! preg_match($pattern, $content, $matches)) {
            return false;
        }

        $currentSection = mb_trim($matches[1]);
        $expectedSection = mb_trim($table);

        return $currentSection === $expectedSection;
    }

    /**
     * Update the feature matrix documentation file on disk.
     */
    public static function updateFile(string $path): bool
    {
        throw_unless(file_exists($path), RuntimeException::class, "Documentation file [{$path}] does not exist.");

        $content = (string) file_get_contents($path);
        $table = self::generateOptionalModulesTable();
        $updated = self::updateContent($content, $table);

        return file_put_contents($path, $updated) !== false;
    }

    /**
     * Check if the feature matrix documentation file on disk is fresh.
     */
    public static function isFileFresh(string $path): bool
    {
        if (! file_exists($path)) {
            return false;
        }

        $content = (string) file_get_contents($path);
        $table = self::generateOptionalModulesTable();

        return self::isContentFresh($content, $table);
    }
}
