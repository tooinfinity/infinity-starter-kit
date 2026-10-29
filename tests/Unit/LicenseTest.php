<?php

declare(strict_types=1);

use App\Chisel\Installer\Cleanup;

$root = dirname(__DIR__, 2);

it('ships a LICENSE file at the repository root', function () use ($root): void {
    expect($root.'/LICENSE')->toBeFile();
});

it('uses the MIT license text with a copyright holder and year', function () use ($root): void {
    $license = (string) file_get_contents($root.'/LICENSE');

    expect($license)
        ->toStartWith('MIT License')
        ->toMatch('/^Copyright \(c\) \d{4} \S.+$/m')
        ->toContain('Permission is hereby granted, free of charge');
});

it('declares the same license in composer.json', function () use ($root): void {
    /** @var array{license?: string} $composer */
    $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);

    expect($composer['license'] ?? null)->toBe('MIT');
});

it('links the README license section to an existing file', function () use ($root): void {
    $readme = (string) file_get_contents($root.'/README.md');

    expect($readme)->toMatch('/\[MIT license\]\((?<target>[^)]+)\)/');

    preg_match('/\[MIT license\]\((?<target>[^)]+)\)/', $readme, $matches);

    expect($root.'/'.$matches['target'])->toBeFile();
});

it('keeps the LICENSE file when the Chisel installer cleans up', function (): void {
    expect(Cleanup::files())->not->toContain('LICENSE');
});
