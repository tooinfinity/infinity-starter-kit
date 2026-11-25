<?php

declare(strict_types=1);

use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

test('RecordAuditTrail stores an activity log record with all provided attributes', function (): void {
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
        ->and($record->id)->toBeString()
        ->and(Str::isUuid($record->id))->toBeTrue()
        ->and($record->user_id)->toBe($actor->id)
        ->and($record->event)->toBe(AuditEvent::UserUpdated->value)->and($record->audit_event)->toBe(AuditEvent::UserUpdated)
        ->and($record->auditable_type)->toBe($target->getMorphClass())
        ->and($record->auditable_id)->toBe($target->id)
        ->and($record->old_values)->toBe(['name' => 'Old Name'])
        ->and($record->new_values)->toBe(['name' => 'New Name'])
        ->and($record->url)->toBe('http://localhost/users/'.$target->id)
        ->and($record->ip_address)->toBe('192.168.1.100')
        ->and($record->user_agent)->toBe('TestBrowser/1.0')
        ->and($record->tags)->toBe(['users', 'management']);

    $activity = Activity::query()->find($record->id);
    expect($activity)->not->toBeNull()
        ->and($activity)->toBeInstanceOf(Activity::class)
        ->and($activity->id)->toBe($record->id)
        ->and($activity->event)->toBe(AuditEvent::UserUpdated->value)
        ->and($activity->description)->toBe(AuditEvent::UserUpdated->label())
        ->and($activity->causer_type)->toBe($actor->getMorphClass())
        ->and($activity->causer_id)->toBe($actor->id)
        ->and($activity->causer)->toBeInstanceOf(User::class)
        ->and($activity->causer?->id)->toBe($actor->id)
        ->and($activity->subject_type)->toBe($target->getMorphClass())
        ->and($activity->subject_id)->toBe($target->id)
        ->and($activity->subject)->toBeInstanceOf(User::class)
        ->and($activity->subject?->id)->toBe($target->id)
        ->and($activity->attribute_changes)->toBeInstanceOf(Collection::class)
        ->and($activity->attribute_changes?->get('old'))->toBe(['name' => 'Old Name'])
        ->and($activity->attribute_changes?->get('attributes'))->toBe(['name' => 'New Name'])
        ->and($activity->getProperty('request.url'))->toBe('http://localhost/users/'.$target->id)
        ->and($activity->ip_address)->toBe('192.168.1.100')
        ->and($activity->user_agent)->toBe('TestBrowser/1.0')
        ->and($activity->getProperty('tags'))->toBe(['users', 'management'])
        ->and($activity->getProperty('actor.name'))->toBe($actor->name)
        ->and($activity->getProperty('actor.email'))->toBe($actor->email)
        ->and($activity->created_at)->not->toBeNull();
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

    expect($record)->toBeInstanceOf(Activity::class);

    $activity = Activity::query()->find($record->id);
    expect($activity)->not->toBeNull();
    assert($activity instanceof Activity);

    $oldChanges = $activity->attribute_changes?->get('old');
    $newChanges = $activity->attribute_changes?->get('attributes');

    expect($oldChanges['password'])->toBe('[REDACTED]')
        ->and($oldChanges['password_confirmation'])->toBe('[REDACTED]')
        ->and($oldChanges['current_password'])->toBe('[REDACTED]')
        ->and($oldChanges['remember_token'])->toBe('[REDACTED]')
        /* @chisel-two-factor */
        ->and($oldChanges['two_factor_secret'])->toBe('[REDACTED]')
        ->and($oldChanges['two_factor_recovery_codes'])->toBe('[REDACTED]')
        /* @end-chisel-two-factor */
        ->and($oldChanges['api_key'])->toBe('[REDACTED]')
        ->and($oldChanges['app_token'])->toBe('[REDACTED]')
        ->and($oldChanges['safe_field'])->toBe('visible_value')
        ->and($newChanges['password'])->toBe('[REDACTED]')
        ->and($newChanges['nested']['password'])->toBe('[REDACTED]')
        ->and($newChanges['nested']['normal_key'])->toBe('normal_val')
        ->and($newChanges['safe_field'])->toBe('new_visible_value')
        ->and($record->old_values['password'])->toBe('[REDACTED]')
        ->and($record->new_values['password'])->toBe('[REDACTED]');
});

test('RecordAuditTrail handles anonymous or system actions without an actor', function (): void {
    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['app.name' => 'Old'],
        newValues: ['app.name' => 'New'],
    );

    expect($record)->toBeInstanceOf(Activity::class)
        ->and($record->user_id)->toBeNull()
        ->and($record->user)->toBeNull();

    $activity = Activity::query()->find($record->id);
    expect($activity)->not->toBeNull();
    assert($activity instanceof Activity);

    expect($activity->causer_id)->toBeNull()
        ->and($activity->causer)->toBeNull()
        ->and($activity->getProperty('actor.name'))->toBeNull()
        ->and($activity->getProperty('actor.email'))->toBeNull();
});

test('RecordAuditTrail falls back to authenticated user when actor is not passed', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::UserActivated,
    );

    expect($record)->toBeInstanceOf(Activity::class)
        ->and($record->user_id)->toBe($user->id);

    $activity = Activity::query()->find($record->id);
    expect($activity)->not->toBeNull();
    assert($activity instanceof Activity);

    expect($activity->causer_id)->toBe($user->id)
        ->and($activity->causer?->id)->toBe($user->id);
});

test('keeps the actor identity after the actor is deleted', function (): void {
    $actor = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com']);
    $this->actingAs($actor);

    $record = resolve(RecordAuditTrail::class)->handle(event: AuditEvent::SettingsUpdated);
    expect($record)->toBeInstanceOf(Activity::class);

    $actor->delete();

    $record->refresh();
    expect($record->user_id)->toBe($actor->id)
        ->and($record->actor_email)->toBe('ada@example.com')
        ->and($record->actor_name)->toBe('Ada');

    $activity = Activity::query()->find($record->id);
    expect($activity)->not->toBeNull();
    assert($activity instanceof Activity);

    $activity->refresh();
    expect($activity->causer)->toBeNull()
        ->and($activity->getProperty('actor.email'))->toBe('ada@example.com')
        ->and($activity->getProperty('actor.name'))->toBe('Ada');
});

test('RecordAuditTrail guarantees activity recording even when activitylog.enabled is false', function (): void {
    config()->set('activitylog.enabled', false);

    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['key' => 'old'],
        newValues: ['key' => 'new'],
    );

    expect($record)->toBeInstanceOf(Activity::class)
        ->and($record->id)->toBeString()
        ->and(Activity::query()->count())->toBe(1)
        ->and(Activity::query()->count())->toBe(1);

    $activity = Activity::query()->find($record->id);
    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe(AuditEvent::SettingsUpdated->value)
        ->and($activity->attribute_changes?->get('attributes'))->toBe(['key' => 'new']);
});

test('RecordAuditTrail handles custom auditableType and auditableId strings', function (): void {
    $action = resolve(RecordAuditTrail::class);
    $uuid = (string) Str::uuid();

    $record = $action->handle(
        event: AuditEvent::UserCreated,
        auditableType: User::class,
        auditableId: $uuid,
    );

    expect($record)->toBeInstanceOf(Activity::class)
        ->and($record->auditable_type)->toBe(User::class)
        ->and($record->auditable_id)->toBe($uuid);

    $activity = Activity::query()->find($record->id);
    expect($activity)->not->toBeNull();
    assert($activity instanceof Activity);

    expect($activity->subject_type)->toBe(User::class)
        ->and($activity->subject_id)->toBe($uuid);
});

test('RecordAuditTrail handles console execution without bound request', function (): void {
    app()->forgetInstance('request');
    $action = new RecordAuditTrail();

    $record = $action->handle(
        event: AuditEvent::SettingsUpdated,
    );

    expect($record)->toBeInstanceOf(Activity::class)
        ->and($record->ip_address)->toBeNull()
        ->and($record->user_agent)->toBeNull()
        ->and($record->url)->toBeNull();

    $activity = Activity::query()->find($record->id);
    expect($activity)->not->toBeNull();
    assert($activity instanceof Activity);

    expect($activity->ip_address)->toBeNull()
        ->and($activity->user_agent)->toBeNull()
        ->and($activity->getProperty('request.url'))->toBeNull();
});

test('RecordAuditTrail creates exactly one activity log record per invocation without duplication', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $action = resolve(RecordAuditTrail::class);

    $initialActivityCount = Activity::query()->count();
    $initialAuditTrailCount = Activity::query()->count();

    $record = $action->handle(
        event: AuditEvent::UserUpdated,
        auditable: $target,
        oldValues: ['name' => 'Initial'],
        newValues: ['name' => 'Updated'],
        user: $actor,
    );

    expect($record)->toBeInstanceOf(Activity::class)
        ->and(Activity::query()->count())->toBe($initialActivityCount + 1)
        ->and(Activity::query()->count())->toBe($initialAuditTrailCount + 1);

    $activity = Activity::query()->find($record->id);
    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe(AuditEvent::UserUpdated->value);
});

test('activity model hides properties and attribute_changes upon serialization', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $record = resolve(RecordAuditTrail::class)->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['secret_token' => 'topsecret'],
        newValues: ['secret_token' => 'newsecret'],
    );

    expect($record)->toBeInstanceOf(Activity::class);

    $activity = Activity::query()->find($record->id);
    expect($activity)->not->toBeNull();
    assert($activity instanceof Activity);

    $serialized = $activity->toArray();
    expect($serialized)->not->toHaveKey('properties')
        ->and($serialized)->not->toHaveKey('attribute_changes');
});
