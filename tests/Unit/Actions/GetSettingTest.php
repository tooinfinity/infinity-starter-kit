<?php

declare(strict_types=1);

use App\Actions\GetSetting;
use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

test('GetSetting excludes unsupported stored keys before hydrating settings', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Custom App',
    ]);

    DB::table('settings')->insert([
        'id' => (string) Str::uuid(),
        'key' => 'legacy.setting',
        'value' => json_encode('Legacy value', JSON_THROW_ON_ERROR),
    ]);

    $hydratedKeys = [];

    Setting::retrieved(function (Setting $setting) use (&$hydratedKeys): void {
        $hydratedKeys[] = $setting->getRawOriginal('key');
    });

    expect(resolve(GetSetting::class)->all())->toBe([
        SettingKey::ApplicationName->value => 'Custom App',
    ])->and($hydratedKeys)->toBe([SettingKey::ApplicationName->value]);
});
