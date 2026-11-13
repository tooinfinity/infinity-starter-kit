<?php

declare(strict_types=1);

use App\Actions\UpdateSettings;
use App\Enums\SettingKey;
use App\Models\Setting;
use App\Queries\SettingQuery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
    $settingQuery = resolve(SettingQuery::class);
    $updateSettings = resolve(UpdateSettings::class);

    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Before Update',
    ]);

    $settingQuery->get(SettingKey::ApplicationName);

    expect(Cache::has('settings.all'))->toBeTrue();

    $updateSettings->handle([
        SettingKey::ApplicationName->value => 'After Update',
    ]);

    expect(Cache::has('settings.all'))->toBeFalse();
});

test('UpdateSettings invalidates cache after its transaction completes', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Before Update',
    ]);

    $transactionLevel = DB::transactionLevel();

    Cache::shouldReceive('forget')
        ->once()
        ->with('settings.all')
        ->andReturnUsing(function () use ($transactionLevel): bool {
            expect(DB::transactionLevel())->toBe($transactionLevel);

            return true;
        });

    resolve(UpdateSettings::class)->set(SettingKey::ApplicationName, 'After Update');
});

test('UpdateSettings set method works with SettingKey enum', function (): void {
    $action = resolve(UpdateSettings::class);
    $action->set(SettingKey::ApplicationName, 'Enum Set');

    $setting = Setting::query()
        ->where('key', SettingKey::ApplicationName->value)
        ->first();

    expect($setting->value)->toBe('Enum Set');
});

test('UpdateSettings skips unchanged settings and does not bust cache', function (): void {
    Setting::factory()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Same Name',
    ]);

    Cache::spy();

    $action = resolve(UpdateSettings::class);
    $action->handle([
        SettingKey::ApplicationName->value => 'Same Name',
    ]);

    Cache::shouldNotHaveReceived('forget');
});

test('UpdateSettings returns early and does not bust cache when settings array is empty', function (): void {
    Cache::spy();

    $action = resolve(UpdateSettings::class);
    $action->handle([]);

    Cache::shouldNotHaveReceived('forget');
});

test('UpdateSettings only updates changed settings and busts cache when one of multiple settings changes', function (): void {
    Setting::factory()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Same Name',
    ]);
    Setting::factory()->create([
        'key' => SettingKey::ApplicationTimezone->value,
        'value' => 'UTC',
    ]);

    Cache::spy();

    $action = resolve(UpdateSettings::class);
    $action->handle([
        SettingKey::ApplicationName->value => 'Same Name',
        SettingKey::ApplicationTimezone->value => 'Europe/London',
    ]);

    Cache::shouldHaveReceived('forget')->with('settings.all')->once();

    $name = Setting::query()->where('key', SettingKey::ApplicationName->value)->first();
    $tz = Setting::query()->where('key', SettingKey::ApplicationTimezone->value)->first();

    expect($name?->value)->toBe('Same Name')
        ->and($tz?->value)->toBe('Europe/London');
});

test('UpdateSettings recovers from unique constraint conflict when setting is concurrently created', function (): void {
    $inserted = false;
    DB::listen(function ($query) use (&$inserted): void {
        if (! $inserted && preg_match('/where\s+[`"]?key[`"]?\s+in/i', $query->sql)) {
            $inserted = true;
            Setting::factory()->create([
                'key' => SettingKey::ApplicationName->value,
                'value' => 'Concurrent Value',
            ]);
        }
    });

    $action = resolve(UpdateSettings::class);
    $action->handle([
        SettingKey::ApplicationName->value => 'Final Value',
    ]);

    expect($inserted)->toBeTrue();

    $setting = Setting::query()->where('key', SettingKey::ApplicationName->value)->first();
    expect($setting?->value)->toBe('Final Value');
});

test('UpdateSettings busts cache but skips update when concurrent insert matches submitted value', function (): void {
    $inserted = false;
    DB::listen(function ($query) use (&$inserted): void {
        if (! $inserted && preg_match('/where\s+[`"]?key[`"]?\s+in/i', $query->sql)) {
            $inserted = true;
            Setting::factory()->create([
                'key' => SettingKey::ApplicationName->value,
                'value' => 'Matching Value',
            ]);
        }
    });

    Cache::spy();

    $action = resolve(UpdateSettings::class);
    $action->handle([
        SettingKey::ApplicationName->value => 'Matching Value',
    ]);

    expect($inserted)->toBeTrue();

    Cache::shouldHaveReceived('forget')->with('settings.all')->once();
});
