<?php

declare(strict_types=1);

/* @chisel-reporting */

test('reporting feature is registered in chisel configuration', function (): void {
    if (! file_exists(base_path('chisel.php'))) {
        $this->markTestSkipped('Chisel has already been applied and removed.');
    }

    $chiselContent = (string) file_get_contents(base_path('chisel.php'));

    expect($chiselContent)->toContain("'reporting' => 'Reporting'");
});

test('reporting chisel markers exist symmetrically in modified existing files', function (): void {
    $files = [
        'app/Enums/Permission.php',
        'routes/web.php',
        'resources/js/types/index.ts',
        'resources/js/components/app-sidebar.tsx',
        'tests/Unit/Enums/PermissionTest.php',
        'tests/Feature/InstallFeaturesCommandTest.php',
    ];

    foreach ($files as $file) {
        $content = (string) file_get_contents(base_path($file));

        $opens = mb_substr_count($content, '@chisel-reporting');
        $closes = mb_substr_count($content, '@end-chisel-reporting');

        expect($opens)
            ->toBeGreaterThan(0, "File {$file} is missing @chisel-reporting")
            ->and($opens)
            ->toBe($closes, "File {$file} has mismatched @chisel-reporting ({$opens}) and @end-chisel-reporting ({$closes})");
    }
});

/* @end-chisel-reporting */
