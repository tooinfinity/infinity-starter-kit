<?php

declare(strict_types=1);

use App\Chisel\Installer\GeneratedApplicationManifest;
use App\Chisel\Installer\Preset;
use App\Chisel\ManifestExampleGenerator;
use Illuminate\Support\Facades\File;

test('generateExampleJson returns deterministic valid json matching canonical schema', function (): void {
    $json = ManifestExampleGenerator::generateExampleJson();

    expect(json_validate($json))->toBeTrue();

    /** @var array<string, mixed> $data */
    $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    expect($data['schema_version'])->toBe(GeneratedApplicationManifest::SCHEMA_VERSION)
        ->and($data['preset'])->toBe(Preset::STANDARD)
        ->and($data['starter_kit']['name'])->toBe('tooinfinity/infinity-starter-kit')
        ->and($data['starter_kit']['version'])->toBe('1.0.0')
        ->and($data['starter_kit']['source_revision'])->toBe('1c253c2000000000000000000000000000000000')
        ->and($data['generated_at'])->toBe('2026-11-02T22:00:00Z')
        ->and($data)->toHaveKeys(['schema_version', 'starter_kit', 'preset', 'features', 'auth_capabilities', 'generated_at']);
});

test('generateBlock wraps example json in markdown code block', function (): void {
    $block = ManifestExampleGenerator::generateBlock();

    expect(str_starts_with($block, "```json\n"))->toBeTrue()
        ->and(str_ends_with($block, "\n```"))->toBeTrue()
        ->and($block)->toContain('"schema_version": 1');
});

test('hasMarkers accurately detects presence of manifest markers', function (): void {
    $withMarkers = "Intro\n".ManifestExampleGenerator::START_MARKER."\ncontent\n".ManifestExampleGenerator::END_MARKER."\nOutro";
    $missingEnd = "Intro\n".ManifestExampleGenerator::START_MARKER."\ncontent";
    $missingStart = "Intro\ncontent\n".ManifestExampleGenerator::END_MARKER;
    $noMarkers = 'Plain text without markers';

    expect(ManifestExampleGenerator::hasMarkers($withMarkers))->toBeTrue()
        ->and(ManifestExampleGenerator::hasMarkers($missingEnd))->toBeFalse()
        ->and(ManifestExampleGenerator::hasMarkers($missingStart))->toBeFalse()
        ->and(ManifestExampleGenerator::hasMarkers($noMarkers))->toBeFalse();
});

test('updateContent replaces section between markers', function (): void {
    $content = "# Title\n\n".ManifestExampleGenerator::START_MARKER."\nold content\n".ManifestExampleGenerator::END_MARKER."\n\n# Footer";
    $customBlock = "```json\n{\"test\": true}\n```";

    $updated = ManifestExampleGenerator::updateContent($content, $customBlock);
    $expected = "# Title\n\n".ManifestExampleGenerator::START_MARKER."\n".$customBlock."\n".ManifestExampleGenerator::END_MARKER."\n\n# Footer";

    expect($updated)->toBe($expected);
});

test('updateContent throws RuntimeException when markers are missing', function (): void {
    expect(fn (): string => ManifestExampleGenerator::updateContent('No markers here'))
        ->toThrow(RuntimeException::class, 'Could not find manifest example markers');
});

test('isContentFresh detects matching, stale, and missing marker content', function (): void {
    $block = ManifestExampleGenerator::generateBlock();
    $fresh = "Header\n".ManifestExampleGenerator::START_MARKER."\n".$block."\n".ManifestExampleGenerator::END_MARKER."\nFooter";
    $stale = "Header\n".ManifestExampleGenerator::START_MARKER."\n```json\n{}\n```\n".ManifestExampleGenerator::END_MARKER."\nFooter";
    $noMarkers = 'Header and Footer';

    expect(ManifestExampleGenerator::isContentFresh($fresh))->toBeTrue()
        ->and(ManifestExampleGenerator::isContentFresh($stale))->toBeFalse()
        ->and(ManifestExampleGenerator::isContentFresh($noMarkers))->toBeFalse();
});

test('updateFile and isFileFresh operate correctly on disk files', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'manifest_docs_test_');
    expect($tempFile)->not->toBeFalse();

    try {
        $initial = "Prefix\n".ManifestExampleGenerator::START_MARKER."\n```json\n{\"stale\": true}\n```\n".ManifestExampleGenerator::END_MARKER."\nSuffix";
        file_put_contents($tempFile, $initial);

        expect(ManifestExampleGenerator::isFileFresh($tempFile))->toBeFalse();

        $updated = ManifestExampleGenerator::updateFile($tempFile);
        expect($updated)->toBeTrue()
            ->and(ManifestExampleGenerator::isFileFresh($tempFile))->toBeTrue();

        $content = (string) file_get_contents($tempFile);
        expect($content)->toContain('"schema_version": 1')
            ->and($content)->toContain(ManifestExampleGenerator::START_MARKER)
            ->and($content)->toContain(ManifestExampleGenerator::END_MARKER);
    } finally {
        File::delete($tempFile);
    }
});

test('updateFile throws when target file does not exist', function (): void {
    expect(fn (): bool => ManifestExampleGenerator::updateFile('/non/existent/path/docs.md'))
        ->toThrow(RuntimeException::class, 'does not exist');
});

test('isFileFresh returns false for non-existent file', function (): void {
    expect(ManifestExampleGenerator::isFileFresh('/non/existent/path/docs.md'))->toBeFalse();
});
