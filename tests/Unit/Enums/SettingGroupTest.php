<?php

declare(strict_types=1);

use App\Enums\SettingGroup;

/* @chisel-settings */

test('all setting group values are unique', function (): void {
    $values = SettingGroup::values();

    expect($values)->toHaveCount(count(array_unique($values)));
});

test('values helper returns all setting group strings', function (): void {
    $values = SettingGroup::values();

    expect($values)->toBe([
        'application',
    ]);
});

test('each setting group is a string-backed enum', function (): void {
    foreach (SettingGroup::cases() as $case) {
        expect($case->value)->toBeString();
    }
});

test('label returns a human-readable label', function (): void {
    expect(SettingGroup::Application->label())->toBe('Application');
});

/* @end-chisel-settings */
