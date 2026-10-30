<?php

declare(strict_types=1);

use App\Chisel\FeatureRegistry;
use App\Chisel\Installer\DependencyValidator;
use App\Chisel\Installer\Preset;

test('preset names returns all defined preset values', function (): void {
    expect(Preset::names())->toBe(['minimal', 'standard', 'full']);
});

test('preset all returns preset instances for all defined names', function (): void {
    $all = Preset::all();

    expect($all)->toHaveCount(3)
        ->and(array_map(fn (Preset $p): string => $p->name, $all))->toBe(Preset::names());
});

test('preset fromName resolves valid preset names case-insensitively with trimming', function (string $input, string $expectedName): void {
    $preset = Preset::fromName($input);

    expect($preset->name)->toBe($expectedName);
})->with([
    'minimal exact' => ['minimal', Preset::MINIMAL],
    'minimal uppercase' => ['MINIMAL', Preset::MINIMAL],
    'minimal padded' => ['  minimal  ', Preset::MINIMAL],
    'standard exact' => ['standard', Preset::STANDARD],
    'standard mixed' => ['Standard', Preset::STANDARD],
    'full exact' => ['full', Preset::FULL],
    'full uppercase' => ['FULL', Preset::FULL],
]);

test('preset fromName throws InvalidArgumentException for unknown preset names', function (string $invalid): void {
    expect(fn (): Preset => Preset::fromName($invalid))
        ->toThrow(InvalidArgumentException::class, "Unknown preset [{$invalid}]. Available presets: minimal, standard, full.");
})->with([
    'empty' => [''],
    'unknown' => ['unknown'],
    'custom' => ['custom-preset'],
]);

test('preset answersFor throws InvalidArgumentException for unknown preset name', function (): void {
    expect(fn (): array => Preset::answersFor('unknown'))
        ->toThrow(InvalidArgumentException::class, 'Unknown preset [unknown]. Available presets: minimal, standard, full.');
});

test('every preset passes dependency validation', function (Preset $preset): void {
    expect(fn () => DependencyValidator::validate($preset->answers()))
        ->not->toThrow(Throwable::class);
})->with(Preset::all());

test('all features in presets exist in feature registry', function (Preset $preset): void {
    $validKeys = array_keys(FeatureRegistry::all());

    $answers = $preset->answers();
    foreach ($answers['auth_features'] as $feature) {
        expect($validKeys)->toContain($feature);
    }

    foreach ($answers['optional_modules'] as $module) {
        expect($validKeys)->toContain($module);
    }
})->with(Preset::all());

test('minimal preset provides core auth surface with no optional modules', function (): void {
    $answers = new Preset(Preset::MINIMAL)->answers();

    expect($answers)->toBe(Preset::answersFor(Preset::MINIMAL))
        ->and($answers['auth_features'])->toBe(['login', 'logout'])
        ->and($answers['optional_modules'])->toBe([]);
});

test('standard preset provides default application features', function (): void {
    $answers = new Preset(Preset::STANDARD)->answers();

    expect($answers)->toBe(Preset::answersFor(Preset::STANDARD))
        ->and($answers['auth_features'])->toBe([
            'login',
            'logout',
            'registration',
            'email-verification',
            'password-reset',
            'password-update',
        ])->and($answers['optional_modules'])->toBe([
            'authorization',
            'settings',
            'user-management',
            'notifications',
        ]);
});

test('full preset provides all available features', function (): void {
    $answers = new Preset(Preset::FULL)->answers();

    expect($answers)->toBe(Preset::answersFor(Preset::FULL))
        ->and($answers['auth_features'])->toBe([
            'login',
            'logout',
            'registration',
            'email-verification',
            'password-reset',
            'password-update',
            'two-factor',
        ])->and($answers['optional_modules'])->toBe([
            'authorization',
            'settings',
            'user-management',
            'localization',
            'notifications',
            'audit-trails',
            'reporting',
        ]);
});
