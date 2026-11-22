<?php

declare(strict_types=1);

use App\Actions\UpdateSettings;
use App\Enums\AuditEvent;
use App\Enums\SettingKey;
use App\Models\Activity;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

test('UpdateSettings records settings.updated audit trail for each setting change', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    Setting::query()->create([
        'key' => SettingKey::ApplicationName,
        'value' => 'Old App Name',
    ]);

    $action = resolve(UpdateSettings::class);
    $action->handle([
        SettingKey::ApplicationName->value => 'New App Name',
        SettingKey::ApplicationTimezone->value => 'America/New_York',
    ]);

    $audits = Activity::query()->where('event', AuditEvent::SettingsUpdated->value)->get();

    expect($audits)->toHaveCount(2);

    $nameAudit = $audits->first(fn (Activity $a): bool => $a->attribute_changes?->get('attributes')['key'] === SettingKey::ApplicationName->value);
    expect($nameAudit)->not->toBeNull()
        ->and($nameAudit->causer_id)->toBe($actor->id)
        ->and($nameAudit->attribute_changes?->get('old')['value'])->toBe('Old App Name')
        ->and($nameAudit->attribute_changes?->get('attributes')['value'])->toBe('New App Name');

    $tzAudit = $audits->first(fn (Activity $a): bool => $a->attribute_changes?->get('attributes')['key'] === SettingKey::ApplicationTimezone->value);
    expect($tzAudit)->not->toBeNull()
        ->and($tzAudit->causer_id)->toBe($actor->id)
        ->and($tzAudit->attribute_changes?->get('old')['value'])->toBeNull()
        ->and($tzAudit->attribute_changes?->get('attributes')['value'])->toBe('America/New_York');
});

test('UpdateSettings set method records settings.updated audit trail', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $action = resolve(UpdateSettings::class);
    $action->set(SettingKey::ApplicationName, 'Custom Name Via Set');

    $audit = Activity::query()->where('event', AuditEvent::SettingsUpdated->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->attribute_changes?->get('attributes')['value'])->toBe('Custom Name Via Set');
});

test('UpdateSettings writes no audit rows when settings values are unchanged', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    Setting::factory()->create([
        'key' => SettingKey::ApplicationName,
        'value' => 'Same Name',
    ]);
    Setting::factory()->create([
        'key' => SettingKey::ApplicationTimezone,
        'value' => 'UTC',
    ]);

    $action = resolve(UpdateSettings::class);
    $action->handle([
        SettingKey::ApplicationName->value => 'Same Name',
        SettingKey::ApplicationTimezone->value => 'UTC',
    ]);

    expect(Activity::query()->where('event', AuditEvent::SettingsUpdated)->count())->toBe(0);
});

test('UpdateSettings writes exactly one audit row when changing only one setting', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    Setting::factory()->create([
        'key' => SettingKey::ApplicationName,
        'value' => 'Same Name',
    ]);
    Setting::factory()->create([
        'key' => SettingKey::ApplicationTimezone,
        'value' => 'UTC',
    ]);

    $action = resolve(UpdateSettings::class);
    $action->handle([
        SettingKey::ApplicationName->value => 'Same Name',
        SettingKey::ApplicationTimezone->value => 'Europe/Paris',
    ]);

    $audits = Activity::query()->where('event', AuditEvent::SettingsUpdated->value)->get();

    expect($audits)->toHaveCount(1);

    $audit = $audits->first();
    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($actor->id)
        ->and($audit->attribute_changes?->get('old')['key'])->toBe(SettingKey::ApplicationTimezone->value)
        ->and($audit->attribute_changes?->get('old')['value'])->toBe('UTC')
        ->and($audit->attribute_changes?->get('attributes')['key'])->toBe(SettingKey::ApplicationTimezone->value)
        ->and($audit->attribute_changes?->get('attributes')['value'])->toBe('Europe/Paris');
});

test('UpdateSettings set method writes no audit rows when setting value is unchanged', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    Setting::factory()->create([
        'key' => SettingKey::ApplicationName,
        'value' => 'Current Name',
    ]);

    $action = resolve(UpdateSettings::class);
    $action->set(SettingKey::ApplicationName, 'Current Name');

    expect(Activity::query()->where('event', AuditEvent::SettingsUpdated)->count())->toBe(0);
});

test('UpdateSettings records recovered oldValue when setting is concurrently created', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

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

    $audit = Activity::query()->where('event', AuditEvent::SettingsUpdated->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->attribute_changes?->get('old')['key'])->toBe(SettingKey::ApplicationName->value)
        ->and($audit->attribute_changes?->get('old')['value'])->toBe('Concurrent Value')
        ->and($audit->attribute_changes?->get('attributes')['key'])->toBe(SettingKey::ApplicationName->value)
        ->and($audit->attribute_changes?->get('attributes')['value'])->toBe('Final Value');
});

test('UpdateSettings writes no audit rows when concurrent insert matches submitted value', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

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

    $action = resolve(UpdateSettings::class);
    $action->handle([
        SettingKey::ApplicationName->value => 'Matching Value',
    ]);

    expect($inserted)->toBeTrue()
        ->and(Activity::query()->where('event', AuditEvent::SettingsUpdated)->count())->toBe(0);
});

test('UpdateSettings uses the latest recovered value for comparison and auditing', function (string $recoveredValue, string $latestValue, array $expectedOldValues): void {
    $inserted = false;
    DB::listen(function ($query) use (&$inserted, $recoveredValue): void {
        if (! $inserted && preg_match('/where\s+[`"]?key[`"]?\s+in/i', $query->sql)) {
            $inserted = true;
            Setting::factory()->create([
                'key' => SettingKey::ApplicationName,
                'value' => $recoveredValue,
            ]);
        }
    });

    $updated = false;
    Setting::retrieved(function (Setting $setting) use (&$updated, $latestValue): void {
        if (! $updated) {
            $updated = true;
            Setting::query()->whereKey($setting->id)->update(['value' => json_encode($latestValue, JSON_THROW_ON_ERROR)]);
        }
    });

    Cache::spy();

    resolve(UpdateSettings::class)->handle([
        SettingKey::ApplicationName->value => 'Final Value',
    ]);

    expect($inserted)->toBeTrue()
        ->and($updated)->toBeTrue();

    $setting = Setting::query()->where('key', SettingKey::ApplicationName->value)->first();
    expect($setting?->value)->toBe('Final Value');

    expect(Activity::query()->where('event', AuditEvent::SettingsUpdated->value)->get()->map(fn (Activity $a) => $a->attribute_changes?->get('old')['value'] ?? null)->filter()->values()->all())
        ->toBe($expectedOldValues);

    Cache::shouldHaveReceived('forget')->with('settings.all')->once();
})->with([
    'audit the latest value' => ['Stale Value', 'Latest Value', ['Latest Value']],
    'skip when the latest value matches' => ['Stale Value', 'Final Value', []],
    'update when only the stale value matches' => ['Final Value', 'Latest Value', ['Latest Value']],
]);
