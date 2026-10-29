<?php

declare(strict_types=1);

use App\Enums\SettingKey;
use App\Models\Setting;
use App\Queries\SettingQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('SettingQuery returns stored value', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Custom App',
    ]);

    $query = resolve(SettingQuery::class);

    expect($query->get(SettingKey::ApplicationName))->toBe('Custom App');
});

test('SettingQuery returns default when setting is not stored', function (): void {
    $query = resolve(SettingQuery::class);

    expect($query->get(SettingKey::ApplicationName))->toBe(config('app.name', 'Laravel'));
});

test('SettingQuery returns default when setting is not stored for timezone', function (): void {
    $query = resolve(SettingQuery::class);

    expect($query->get(SettingKey::ApplicationTimezone))->toBe(config('app.timezone', 'UTC'));
});

test('SettingQuery caches the result', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Cached App',
    ]);

    $query = resolve(SettingQuery::class);
    $query->get(SettingKey::ApplicationName);

    expect(Cache::has('settings.all'))->toBeTrue();
});

test('SettingQuery all returns stored settings', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Custom App',
    ]);

    $query = resolve(SettingQuery::class);

    expect($query->all())->toBe([
        SettingKey::ApplicationName->value => 'Custom App',
    ]);
});

test('SettingQuery excludes unsupported stored keys before hydrating settings', function (): void {
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

    expect(resolve(SettingQuery::class)->all())->toBe([
        SettingKey::ApplicationName->value => 'Custom App',
    ])->and($hydratedKeys)->toBe([SettingKey::ApplicationName->value]);
});
