<?php

declare(strict_types=1);

use App\Actions\GetSetting;
use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

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

    expect(Cache::has('settings.all'))->toBeTrue();
});

test('GetSetting all returns stored settings', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Custom App',
    ]);

    $action = resolve(GetSetting::class);

    expect($action->all())->toBe([
        SettingKey::ApplicationName->value => 'Custom App',
    ]);
});
