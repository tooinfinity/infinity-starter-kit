<?php

declare(strict_types=1);

use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;

test('RecordAuditTrail stores an audit trail record with all provided attributes', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $request = Request::create('/users/'.$target->id, 'PUT', [], [], [], [
        'REMOTE_ADDR' => '192.168.1.100',
        'HTTP_USER_AGENT' => 'TestBrowser/1.0',
    ]);

    $action = new RecordAuditTrail($request);

    $record = $action->handle(
        event: AuditEvent::UserUpdated,
        auditable: $target,
        oldValues: ['name' => 'Old Name'],
        newValues: ['name' => 'New Name'],
        user: $actor,
        tags: ['users', 'management'],
    );

    expect($record)->toBeInstanceOf(Activity::class)
        ->and($record->causer_id)->toBe($actor->id)
        ->and($record->event)->toBe(AuditEvent::UserUpdated->value)
        ->and($record->description)->toBe(AuditEvent::UserUpdated->value)
        ->and($record->log_name)->toBe('users')
        ->and($record->subject_type)->toBe($target->getMorphClass())
        ->and($record->subject_id)->toBe($target->id)
        ->and($record->attribute_changes?->get('old'))->toBe(['name' => 'Old Name'])
        ->and($record->attribute_changes?->get('attributes'))->toBe(['name' => 'New Name'])
        ->and($record->getProperty('request.url'))->toBe('http://localhost/users/'.$target->id)
        ->and($record->ip_address)->toBe('192.168.1.100')
        ->and($record->user_agent)->toBe('TestBrowser/1.0')
        ->and($record->getProperty('tags'))->toBe(['users', 'management'])
        ->and($record->created_at)->not->toBeNull()
        ->and($record->subject)->toBeInstanceOf(User::class)
        ->and($record->subject->id)->toBe($target->id);
});

test('RecordAuditTrail automatically redacts sensitive fields from old and new values', function (): void {
    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::UserCreated,
        oldValues: [
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'current_password' => 'old_secret',
            'remember_token' => 'random_token_string',
            /* @chisel-two-factor */
            'two_factor_secret' => '2fa_secret_key',
            'two_factor_recovery_codes' => ['code1', 'code2'],
            /* @end-chisel-two-factor */
            'api_key' => 'live_12345',
            'app_token' => 'token_xyz',
            'safe_field' => 'visible_value',
        ],
        newValues: [
            'password' => 'new_secret456',
            'nested' => [
                'password' => 'nested_secret',
                'normal_key' => 'normal_val',
            ],
            'safe_field' => 'new_visible_value',
        ],
    );

    $changes = $record->attribute_changes?->toArray() ?? [];
    $old = $changes['old'] ?? [];
    $new = $changes['attributes'] ?? [];

    expect($old['password'])->toBe('[REDACTED]')
        ->and($old['password_confirmation'])->toBe('[REDACTED]')
        ->and($old['current_password'])->toBe('[REDACTED]')
        ->and($old['remember_token'])->toBe('[REDACTED]')
        /* @chisel-two-factor */
        ->and($old['two_factor_secret'])->toBe('[REDACTED]')
        ->and($old['two_factor_recovery_codes'])->toBe('[REDACTED]')
        /* @end-chisel-two-factor */
        ->and($old['api_key'])->toBe('[REDACTED]')
        ->and($old['app_token'])->toBe('[REDACTED]')
        ->and($old['safe_field'])->toBe('visible_value')
        ->and($new['password'])->toBe('[REDACTED]')
        ->and($new['nested']['password'])->toBe('[REDACTED]')
        ->and($new['nested']['normal_key'])->toBe('normal_val')
        ->and($new['safe_field'])->toBe('new_visible_value');
});

test('RecordAuditTrail handles anonymous or system actions without an actor', function (): void {
    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['app.name' => 'Old'],
        newValues: ['app.name' => 'New'],
    );

    expect($record->causer_id)->toBeNull()
        ->and($record->causer)->toBeNull();
});

test('RecordAuditTrail falls back to authenticated user when actor is not passed', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::UserActivated,
    );

    expect($record->causer_id)->toBe($user->id);
});

test('keeps the actor identity after the actor is deleted', function (): void {
    $actor = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com']);
    $this->actingAs($actor);

    $audit = resolve(RecordAuditTrail::class)->handle(event: AuditEvent::SettingsUpdated);
    $actor->delete();

    $audit->refresh();
    expect($audit->causer_id)->toBeNull()
        ->and($audit->getProperty('actor.email'))->toBe('ada@example.com')
        ->and($audit->getProperty('actor.name'))->toBe('Ada');
});

test('RecordAuditTrail respects disabled activity logging without writing records', function (): void {
    config(['activitylog.enabled' => false]);

    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::UserCreated,
        oldValues: ['name' => 'Old'],
        newValues: ['name' => 'New'],
    );

    expect($record)->toBeNull()
        ->and(Activity::query()->count())->toBe(0);
});

test('RecordAuditTrail records subject from auditableType and auditableId strings', function (): void {
    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::SettingsUpdated,
        auditableType: User::class,
        auditableId: 'custom_id_123',
    );

    expect($record)->not->toBeNull()
        ->and($record->subject_type)->toBe(User::class)
        ->and($record->subject_id)->toBe('custom_id_123');
});
