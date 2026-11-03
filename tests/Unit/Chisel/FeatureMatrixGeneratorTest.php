<?php

declare(strict_types=1);

use App\Chisel\FeatureDefinition;
use App\Chisel\FeatureMatrixGenerator;

test('generateTable creates deterministic markdown matrix with headers and formatted cells', function (): void {
    $features = [
        'module-a' => new FeatureDefinition(
            key: 'module-a',
            label: 'Module A',
            sectionMarker: 'module-a',
            composerPackages: ['vendor/pkg-a', 'vendor/pkg-b'],
            frontendPackages: ['@scope/pkg-a'],
            dependencies: ['dep-1', 'dep-2'],
            optionalDependencies: ['opt-1'],
            description: 'Description for module A.',
            capabilities: ['Capability 1', 'Capability 2'],
            permissions: ['perm.a', 'perm.b'],
            default: true,
        ),
        'module-b' => new FeatureDefinition(
            key: 'module-b',
            label: 'Module B',
            sectionMarker: 'module-b',
            description: '',
            default: false,
        ),
    ];

    $table = FeatureMatrixGenerator::generateTable($features);

    $lines = explode("\n", $table);

    expect($lines[0])->toBe('| Module | Identifier | Description | Required Dependencies | Optional Dependencies | Composer Packages | Frontend Packages (Bun) | Permissions | Default | Capabilities |')
        ->and($lines[1])->toBe('| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |')
        ->and($lines[2])->toBe('| **Module A** | `module-a` | Description for module A. | `dep-1`, `dep-2` | `opt-1` | `vendor/pkg-a`, `vendor/pkg-b` | `@scope/pkg-a` | `perm.a`, `perm.b` | Enabled | Capability 1, Capability 2 |')
        ->and($lines[3])->toBe('| **Module B** | `module-b` | — | — | — | — | — | — | Disabled | — |');
});

test('generateOptionalModulesTable generates table containing all registered optional modules', function (): void {
    $table = FeatureMatrixGenerator::generateOptionalModulesTable();

    expect($table)->toContain('| **Authorization** | `authorization` |')
        ->and($table)->toContain('| **Settings** | `settings` |')
        ->and($table)->toContain('| **User Management** | `user-management` |')
        ->and($table)->toContain('| **Localization** | `localization` |')
        ->and($table)->toContain('| **Notifications** | `notifications` |')
        ->and($table)->toContain('| **Audit Trails** | `audit-trails` |')
        ->and($table)->toContain('| **Reporting** | `reporting` |');
});

test('updateContent replaces content between start and end markers', function (): void {
    $content = "# Title\n\n".FeatureMatrixGenerator::START_MARKER."\nold content\n".FeatureMatrixGenerator::END_MARKER."\n\n# Footer";
    $newTable = '| Col 1 | Col 2 |';

    $updated = FeatureMatrixGenerator::updateContent($content, $newTable);

    $expected = "# Title\n\n".FeatureMatrixGenerator::START_MARKER."\n| Col 1 | Col 2 |\n".FeatureMatrixGenerator::END_MARKER."\n\n# Footer";
    expect($updated)->toBe($expected);
});

test('updateContent throws RuntimeException when markers are missing', function (): void {
    $content = '# Document without markers';

    expect(fn (): string => FeatureMatrixGenerator::updateContent($content, 'table'))
        ->toThrow(RuntimeException::class, 'Could not find feature matrix markers');
});

test('isContentFresh detects matching and stale content', function (): void {
    $table = "| Module | Identifier |\n| **Test** | `test` |";
    $freshContent = "Preamble\n".FeatureMatrixGenerator::START_MARKER."\n".$table."\n".FeatureMatrixGenerator::END_MARKER."\nPostamble";
    $staleContent = "Preamble\n".FeatureMatrixGenerator::START_MARKER."\n| Stale |\n".FeatureMatrixGenerator::END_MARKER."\nPostamble";
    $missingMarkers = 'Preamble without markers';

    expect(FeatureMatrixGenerator::isContentFresh($freshContent, $table))->toBeTrue()
        ->and(FeatureMatrixGenerator::isContentFresh($staleContent, $table))->toBeFalse()
        ->and(FeatureMatrixGenerator::isContentFresh($missingMarkers, $table))->toBeFalse();
});

test('updateFile and isFileFresh operate correctly on disk files', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'chisel_docs_test_');
    expect($tempFile)->not->toBeFalse();

    try {
        $initial = "Prefix\n".FeatureMatrixGenerator::START_MARKER."\n| Outdated |\n".FeatureMatrixGenerator::END_MARKER."\nSuffix";
        file_put_contents($tempFile, $initial);

        expect(FeatureMatrixGenerator::isFileFresh($tempFile))->toBeFalse();

        $updated = FeatureMatrixGenerator::updateFile($tempFile);
        expect($updated)->toBeTrue()
            ->and(FeatureMatrixGenerator::isFileFresh($tempFile))->toBeTrue();

        $content = (string) file_get_contents($tempFile);
        expect($content)->toContain('| **Authorization** | `authorization` |')
            ->and($content)->toContain(FeatureMatrixGenerator::START_MARKER)
            ->and($content)->toContain(FeatureMatrixGenerator::END_MARKER);
    } finally {
        @unlink($tempFile);
    }
});

test('updateFile throws when target file does not exist', function (): void {
    expect(fn (): bool => FeatureMatrixGenerator::updateFile('/non/existent/path/docs.md'))
        ->toThrow(RuntimeException::class, 'does not exist');
});

test('isFileFresh returns false for non-existent file', function (): void {
    expect(FeatureMatrixGenerator::isFileFresh('/non/existent/path/docs.md'))->toBeFalse();
});
