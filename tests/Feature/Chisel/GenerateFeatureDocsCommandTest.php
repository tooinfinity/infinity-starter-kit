<?php

declare(strict_types=1);

use App\Chisel\FeatureMatrixGenerator;
use App\Chisel\ManifestExampleGenerator;
use Illuminate\Support\Facades\File;

test('artisan chisel:docs updates documentation file successfully', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'chisel_cmd_test_');
    expect($tempFile)->not->toBeFalse();

    try {
        $initial = "Prefix\n".FeatureMatrixGenerator::START_MARKER."\n| Old Content |\n".FeatureMatrixGenerator::END_MARKER."\nSuffix";
        file_put_contents($tempFile, $initial);

        $this->artisan('chisel:docs', ['--path' => $tempFile])
            ->assertSuccessful();

        $content = (string) file_get_contents($tempFile);
        expect($content)->toContain('| **Authorization** | `authorization` |')
            ->and($content)->not->toContain('| Old Content |');
    } finally {
        File::delete($tempFile);
    }
});

test('artisan chisel:docs updates manifest example when markers are present', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'chisel_cmd_manifest_test_');
    expect($tempFile)->not->toBeFalse();

    try {
        $initial = "Prefix\n".FeatureMatrixGenerator::START_MARKER."\n| Old Content |\n".FeatureMatrixGenerator::END_MARKER."\nMiddle\n"
            .ManifestExampleGenerator::START_MARKER."\n```json\n{\"old\": true}\n```\n".ManifestExampleGenerator::END_MARKER."\nSuffix";
        file_put_contents($tempFile, $initial);

        $this->artisan('chisel:docs', ['--path' => $tempFile])
            ->assertSuccessful();

        $content = (string) file_get_contents($tempFile);
        expect($content)->toContain('| **Authorization** | `authorization` |')
            ->and($content)->toContain('"schema_version": 1')
            ->and($content)->toContain('"preset": "standard"')
            ->and($content)->not->toContain('"old": true');
    } finally {
        File::delete($tempFile);
    }
});

test('artisan chisel:docs --check succeeds when documentation is fresh', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'chisel_cmd_test_');
    expect($tempFile)->not->toBeFalse();

    try {
        $table = FeatureMatrixGenerator::generateOptionalModulesTable();
        $fresh = "Prefix\n".FeatureMatrixGenerator::START_MARKER."\n".$table."\n".FeatureMatrixGenerator::END_MARKER."\nSuffix";
        file_put_contents($tempFile, $fresh);

        $this->artisan('chisel:docs', ['--check' => true, '--path' => $tempFile])
            ->assertSuccessful();
    } finally {
        File::delete($tempFile);
    }
});

test('artisan chisel:docs --check succeeds when both feature matrix and manifest example are fresh', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'chisel_cmd_manifest_test_');
    expect($tempFile)->not->toBeFalse();

    try {
        $table = FeatureMatrixGenerator::generateOptionalModulesTable();
        $block = ManifestExampleGenerator::generateBlock();
        $fresh = "Prefix\n".FeatureMatrixGenerator::START_MARKER."\n".$table."\n".FeatureMatrixGenerator::END_MARKER."\nMiddle\n"
            .ManifestExampleGenerator::START_MARKER."\n".$block."\n".ManifestExampleGenerator::END_MARKER."\nSuffix";
        file_put_contents($tempFile, $fresh);

        $this->artisan('chisel:docs', ['--check' => true, '--path' => $tempFile])
            ->assertSuccessful();
    } finally {
        File::delete($tempFile);
    }
});

test('artisan chisel:docs --check fails when documentation is stale', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'chisel_cmd_test_');
    expect($tempFile)->not->toBeFalse();

    try {
        $stale = "Prefix\n".FeatureMatrixGenerator::START_MARKER."\n| Stale |\n".FeatureMatrixGenerator::END_MARKER."\nSuffix";
        file_put_contents($tempFile, $stale);

        $this->artisan('chisel:docs', ['--check' => true, '--path' => $tempFile])
            ->assertFailed();
    } finally {
        File::delete($tempFile);
    }
});

test('artisan chisel:docs --check fails when manifest example is stale', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'chisel_cmd_manifest_test_');
    expect($tempFile)->not->toBeFalse();

    try {
        $table = FeatureMatrixGenerator::generateOptionalModulesTable();
        $stale = "Prefix\n".FeatureMatrixGenerator::START_MARKER."\n".$table."\n".FeatureMatrixGenerator::END_MARKER."\nMiddle\n"
            .ManifestExampleGenerator::START_MARKER."\n```json\n{\"stale\": true}\n```\n".ManifestExampleGenerator::END_MARKER."\nSuffix";
        file_put_contents($tempFile, $stale);

        $this->artisan('chisel:docs', ['--check' => true, '--path' => $tempFile])
            ->assertFailed();
    } finally {
        File::delete($tempFile);
    }
});

test('artisan chisel:docs reports failure when file does not exist', function (): void {
    $this->artisan('chisel:docs', ['--path' => 'non_existent_file_xyz_123.md'])
        ->assertFailed();
});

test('artisan chisel:docs reports failure when markers are missing', function (): void {
    $tempFile = tempnam(sys_get_temp_dir(), 'chisel_cmd_test_');
    expect($tempFile)->not->toBeFalse();

    try {
        file_put_contents($tempFile, '# Title without chisel markers');

        $this->artisan('chisel:docs', ['--path' => $tempFile])
            ->assertFailed();
    } finally {
        File::delete($tempFile);
    }
});
