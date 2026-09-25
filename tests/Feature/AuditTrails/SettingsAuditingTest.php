<?php

declare(strict_types=1);

use App\Actions\UpdateSettings;
use App\Enums\AuditEvent;
use App\Enums\SettingKey;
use App\Models\AuditTrail;
use App\Models\Setting;
use App\Models\User;

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
