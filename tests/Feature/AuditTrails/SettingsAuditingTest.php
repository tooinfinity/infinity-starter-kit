<?php

declare(strict_types=1);

use App\Actions\UpdateSettings;
use App\Enums\AuditEvent;
use App\Enums\SettingKey;
use App\Models\AuditTrail;
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

    $audits = AuditTrail::query()->where('event', AuditEvent::SettingsUpdated)->get();

    expect($audits)->toHaveCount(2);

    $nameAudit = $audits->firstWhere('new_values.key', SettingKey::ApplicationName->value);
    expect($nameAudit)->not->toBeNull()
        ->and($nameAudit->user_id)->toBe($actor->id)
        ->and($nameAudit->old_values['value'])->toBe('Old App Name')
        ->and($nameAudit->new_values['value'])->toBe('New App Name');

    $tzAudit = $audits->firstWhere('new_values.key', SettingKey::ApplicationTimezone->value);
    expect($tzAudit)->not->toBeNull()
        ->and($tzAudit->user_id)->toBe($actor->id)
        ->and($tzAudit->old_values['value'])->toBeNull()
        ->and($tzAudit->new_values['value'])->toBe('America/New_York');
});

test('UpdateSettings set method records settings.updated audit trail', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $action = resolve(UpdateSettings::class);
    $action->set(SettingKey::ApplicationName, 'Custom Name Via Set');

    $audit = AuditTrail::query()->where('event', AuditEvent::SettingsUpdated)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->new_values['value'])->toBe('Custom Name Via Set');
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

    expect(AuditTrail::query()->where('event', AuditEvent::SettingsUpdated)->count())->toBe(0);
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

    $audits = AuditTrail::query()->where('event', AuditEvent::SettingsUpdated)->get();

    expect($audits)->toHaveCount(1);

    $audit = $audits->first();
    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->old_values['key'])->toBe(SettingKey::ApplicationTimezone->value)
        ->and($audit->old_values['value'])->toBe('UTC')
        ->and($audit->new_values['key'])->toBe(SettingKey::ApplicationTimezone->value)
        ->and($audit->new_values['value'])->toBe('Europe/Paris');
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

    expect(AuditTrail::query()->where('event', AuditEvent::SettingsUpdated)->count())->toBe(0);
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

    $audit = AuditTrail::query()->where('event', AuditEvent::SettingsUpdated)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->old_values['key'])->toBe(SettingKey::ApplicationName->value)
        ->and($audit->old_values['value'])->toBe('Concurrent Value')
        ->and($audit->new_values['key'])->toBe(SettingKey::ApplicationName->value)
        ->and($audit->new_values['value'])->toBe('Final Value');
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
        ->and(AuditTrail::query()->where('event', AuditEvent::SettingsUpdated)->count())->toBe(0);
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

    expect(AuditTrail::query()->where('event', AuditEvent::SettingsUpdated)->get()->pluck('old_values.value')->all())
        ->toBe($expectedOldValues);

    Cache::shouldHaveReceived('forget')->with('settings.all')->once();
})->with([
    'audit the latest value' => ['Stale Value', 'Latest Value', ['Latest Value']],
    'skip when the latest value matches' => ['Stale Value', 'Final Value', []],
    'update when only the stale value matches' => ['Final Value', 'Latest Value', ['Latest Value']],
]);
