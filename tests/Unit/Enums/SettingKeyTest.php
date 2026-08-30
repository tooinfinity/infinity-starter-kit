<?php

declare(strict_types=1);

use App\Enums\SettingGroup;
use App\Enums\SettingKey;

/* @chisel-settings */

test('all setting key values are unique', function (): void {
    $values = SettingKey::values();

    expect($values)->toHaveCount(count(array_unique($values)));
});

test('values helper returns all setting key strings', function (): void {
    $values = SettingKey::values();

    expect($values)->toBe([
        'application.name',
        'application.timezone',
    ]);
});

test('each setting key is a string-backed enum', function (): void {
    foreach (SettingKey::cases() as $case) {
        expect($case->value)->toBeString();
    }
});

test('defaultValue returns expected defaults', function (): void {
    expect(SettingKey::ApplicationName->defaultValue())->toBe(config('app.name', 'Laravel'))
        ->and(SettingKey::ApplicationTimezone->defaultValue())->toBe(config('app.timezone', 'UTC'));
});

test('label returns a human-readable label', function (): void {
    expect(SettingKey::ApplicationName->label())->toBe('Application Name')
        ->and(SettingKey::ApplicationTimezone->label())->toBe('Timezone');
});

test('group returns the expected SettingGroup', function (): void {
    expect(SettingKey::ApplicationName->group())->toBe(SettingGroup::Application)
        ->and(SettingKey::ApplicationTimezone->group())->toBe(SettingGroup::Application);
});

test('rules returns non-empty validation rules', function (): void {
    foreach (SettingKey::cases() as $case) {
        expect($case->rules())->toBeArray()->not->toBeEmpty();
    }
});

/* @end-chisel-settings */
