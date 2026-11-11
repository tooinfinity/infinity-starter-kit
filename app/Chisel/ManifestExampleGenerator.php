<?php

declare(strict_types=1);

namespace App\Chisel;

use App\Chisel\Installer\GeneratedApplicationManifest;
use RuntimeException;

/**
 * Deterministic markdown documentation generator for the starter kit manifest example.
 */
final readonly class ManifestExampleGenerator
{
    public const string START_MARKER = '<!-- @chisel-manifest-example -->';

    public const string END_MARKER = '<!-- @end-chisel-manifest-example -->';

    /**
     * Generate the deterministic JSON string for the canonical starter kit manifest example.
     */
    public static function generateExampleJson(): string
    {
        return GeneratedApplicationManifest::example()->toJson();
    }

    /**
     * Generate the markdown block containing the json example.
     */
    public static function generateBlock(): string
    {
        return "```json\n".self::generateExampleJson()."\n```";
    }

    /**
     * Check if markers are present in the given content.
     */
    public static function hasMarkers(string $content): bool
    {
        return str_contains($content, self::START_MARKER) && str_contains($content, self::END_MARKER);
    }

    /**
     * Replace the marked manifest example section in the provided documentation content.
     */
    public static function updateContent(string $content, ?string $block = null): string
    {
        $block ??= self::generateBlock();

        $pattern = '/('.preg_quote(self::START_MARKER, '/').')(.*?)('.preg_quote(self::END_MARKER, '/').')/s';

        if (! preg_match($pattern, $content)) {
            throw new RuntimeException(sprintf(
                'Could not find manifest example markers [%s] and [%s] in documentation.',
                self::START_MARKER,
                self::END_MARKER,
            ));
        }

        $replacement = self::START_MARKER."\n".$block."\n".self::END_MARKER;

        return (string) preg_replace($pattern, $replacement, $content);
    }

    /**
     * Check if the marked manifest example section in the content matches the expected block.
     */
    public static function isContentFresh(string $content, ?string $block = null): bool
    {
        $block ??= self::generateBlock();

        $pattern = '/'.preg_quote(self::START_MARKER, '/').'(.*?)'.preg_quote(self::END_MARKER, '/').'/s';

        if (! preg_match($pattern, $content, $matches)) {
            return false;
        }

        $currentSection = mb_trim($matches[1]);
        $expectedSection = mb_trim($block);

        return $currentSection === $expectedSection;
    }

    /**
     * Update the manifest example documentation file on disk.
     */
    public static function updateFile(string $path): bool
    {
        throw_unless(file_exists($path), RuntimeException::class, "Documentation file [{$path}] does not exist.");

        $content = (string) file_get_contents($path);
        $updated = self::updateContent($content);

        return file_put_contents($path, $updated) !== false;
    }

    /**
     * Check if the manifest example in the documentation file on disk is fresh.
     */
    public static function isFileFresh(string $path): bool
    {
        if (! file_exists($path)) {
            return false;
        }

        $content = (string) file_get_contents($path);

        return self::isContentFresh($content);
    }
}
