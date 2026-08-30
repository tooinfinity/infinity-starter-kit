<?php

declare(strict_types=1);

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Database\UniqueConstraintViolationException;

/* @chisel-settings */

test('a setting can be created and retrieved', function (): void {
    $setting = Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'My Application',
    ]);

    expect($setting->key)->toBe(SettingKey::ApplicationName)
        ->and($setting->value)->toBe('My Application');
});

test('json values are correctly stored and retrieved', function (): void {
    $setting = Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => ['en', 'fr', 'ar'],
    ]);

    $setting->refresh();

    expect($setting->value)->toBe(['en', 'fr', 'ar']);
});

test('boolean values are correctly stored and retrieved', function (): void {
    $setting = Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => true,
    ]);

    $setting->refresh();

    expect($setting->value)->toBeTrue();
});

test('null values are correctly stored and retrieved', function (): void {
    $setting = Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => null,
    ]);

    $setting->refresh();

    expect($setting->value)->toBeNull();
});

test('key is cast to SettingKey enum', function (): void {
    $setting = Setting::query()->create([
        'key' => SettingKey::ApplicationTimezone->value,
        'value' => 'UTC',
    ]);

    expect($setting->key)->toBeInstanceOf(SettingKey::class)
        ->and($setting->key)->toBe(SettingKey::ApplicationTimezone);
});

test('key is unique', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'First',
    ]);

    expect(fn () => Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Second',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

/* @end-chisel-settings */
