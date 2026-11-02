<?php

declare(strict_types=1);

use App\Chisel\FeatureMatrixGenerator;
use App\Chisel\FeatureRegistry;

test('committed README feature matrix is up to date with authoritative feature definitions', function (): void {
    $readmePath = base_path('README.md');
    expect(file_exists($readmePath))->toBeTrue();

    $content = (string) file_get_contents($readmePath);
    $table = FeatureMatrixGenerator::generateOptionalModulesTable();

    expect(FeatureMatrixGenerator::isContentFresh($content, $table))
        ->toBeTrue('The feature compatibility matrix in README.md is stale. Run `php artisan chisel:docs` to synchronize it.');
});

test('committed README feature matrix includes every registered optional module', function (): void {
    $readmePath = base_path('README.md');
    $content = (string) file_get_contents($readmePath);

    foreach (FeatureRegistry::optionalModules() as $feature) {
        expect($content)->toContain("**{$feature->label}**")
            ->and($content)->toContain("`{$feature->key}`");
    }
});
