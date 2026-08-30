<?php

declare(strict_types=1);

use App\Actions\GetSetting;
use App\Actions\UpdateSettings;
use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/* @chisel-settings */

test('GetSetting returns stored value', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Custom App',
    ]);

    $action = resolve(GetSetting::class);

    expect($action->handle(SettingKey::ApplicationName))->toBe('Custom App');
});

test('GetSetting returns default when setting is not stored', function (): void {
    $action = resolve(GetSetting::class);

    expect($action->handle(SettingKey::ApplicationName))->toBe(config('app.name', 'Laravel'));
});

test('GetSetting returns default when setting is not stored for timezone', function (): void {
    $action = resolve(GetSetting::class);

    expect($action->handle(SettingKey::ApplicationTimezone))->toBe(config('app.timezone', 'UTC'));
});

test('GetSetting caches the result', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Cached App',
    ]);

    $action = resolve(GetSetting::class);
    $action->handle(SettingKey::ApplicationName);

    expect(Cache::has('settings.application.name'))->toBeTrue();
});

test('UpdateSettings persists a single setting', function (): void {
    $action = resolve(UpdateSettings::class);

    $action->handle([
        SettingKey::ApplicationName->value => 'New Name',
    ]);

    $setting = Setting::query()
        ->where('key', SettingKey::ApplicationName->value)
        ->first();

    expect($setting)->not->toBeNull()
        ->and($setting->value)->toBe('New Name');
});

test('UpdateSettings persists multiple settings', function (): void {
    $action = resolve(UpdateSettings::class);

    $action->handle([
        SettingKey::ApplicationName->value => 'Multi Name',
        SettingKey::ApplicationTimezone->value => 'Europe/London',
    ]);

    expect(Setting::query()->count())->toBe(2);
});

test('UpdateSettings updates existing settings', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Old Name',
    ]);

    $action = resolve(UpdateSettings::class);
    $action->handle([
        SettingKey::ApplicationName->value => 'Updated Name',
    ]);

    $setting = Setting::query()
        ->where('key', SettingKey::ApplicationName->value)
        ->first();

    expect($setting->value)->toBe('Updated Name');
});

test('UpdateSettings invalidates cache', function (): void {
    $getSetting = resolve(GetSetting::class);
    $updateSettings = resolve(UpdateSettings::class);

    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Before Update',
    ]);

    $getSetting->handle(SettingKey::ApplicationName);

    expect(Cache::has('settings.application.name'))->toBeTrue();

    $updateSettings->handle([
        SettingKey::ApplicationName->value => 'After Update',
    ]);

    expect(Cache::has('settings.application.name'))->toBeFalse();
});

test('UpdateSettings set method works with SettingKey enum', function (): void {
    $action = resolve(UpdateSettings::class);
    $action->set(SettingKey::ApplicationName, 'Enum Set');

    $setting = Setting::query()
        ->where('key', SettingKey::ApplicationName->value)
        ->first();

    expect($setting->value)->toBe('Enum Set');
});

/* @end-chisel-settings */
