<?php

declare(strict_types=1);

use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\AuditTrail;
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
        ->and($record->event)->toBe(AuditEvent::UserUpdated->value)
        ->and($record->description)->toBe(AuditEvent::UserUpdated->label())
        ->and($record->causer_type)->toBe($actor->getMorphClass())
        ->and($record->causer_id)->toBe($actor->id)
        ->and($record->causer)->toBeInstanceOf(User::class)
        ->and($record->causer?->id)->toBe($actor->id)
        ->and($record->subject_type)->toBe($target->getMorphClass())
        ->and($record->subject_id)->toBe($target->id)
        ->and($record->subject)->toBeInstanceOf(User::class)
        ->and($record->subject?->id)->toBe($target->id)
        ->and($record->attribute_changes)->toBeInstanceOf(Collection::class)
        ->and($record->attribute_changes?->get('old'))->toBe(['name' => 'Old Name'])
        ->and($record->attribute_changes?->get('attributes'))->toBe(['name' => 'New Name'])
        ->and($record->getProperty('request.url'))->toBe('http://localhost/users/'.$target->id)
        ->and($record->ip_address)->toBe('192.168.1.100')
        ->and($record->user_agent)->toBe('TestBrowser/1.0')
        ->and($record->getProperty('tags'))->toBe(['users', 'management'])
        ->and($record->getProperty('actor.name'))->toBe($actor->name)
        ->and($record->getProperty('actor.email'))->toBe($actor->email)
        ->and($record->created_at)->not->toBeNull();

    $legacy = AuditTrail::query()->find($record->id);
    expect($legacy)->not->toBeNull()
        ->and($legacy->user_id)->toBe($actor->id)
        ->and($legacy->event)->toBe(AuditEvent::UserUpdated)
        ->and($legacy->auditable_type)->toBe($target->getMorphClass())
        ->and($legacy->auditable_id)->toBe($target->id)
        ->and($legacy->old_values)->toBe(['name' => 'Old Name'])
        ->and($legacy->new_values)->toBe(['name' => 'New Name'])
        ->and($legacy->url)->toBe('http://localhost/users/'.$target->id)
        ->and($legacy->ip_address)->toBe('192.168.1.100')
        ->and($legacy->user_agent)->toBe('TestBrowser/1.0')
        ->and($legacy->tags)->toBe(['users', 'management']);
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

    expect($record)->not->toBeNull();
    assert($record instanceof Activity);

    $oldChanges = $record->attribute_changes?->get('old');
    $newChanges = $record->attribute_changes?->get('attributes');

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
        ->and($newChanges['safe_field'])->toBe('new_visible_value');

    $legacy = AuditTrail::query()->find($record->id);
    expect($legacy)->not->toBeNull()
        ->and($legacy->old_values['password'])->toBe('[REDACTED]')
        ->and($legacy->new_values['password'])->toBe('[REDACTED]');
});

test('RecordAuditTrail handles anonymous or system actions without an actor', function (): void {
    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['app.name' => 'Old'],
        newValues: ['app.name' => 'New'],
    );

    expect($record)->not->toBeNull();
    assert($record instanceof Activity);

    expect($record->causer_id)->toBeNull()
        ->and($record->causer)->toBeNull()
        ->and($record->getProperty('actor.name'))->toBeNull()
        ->and($record->getProperty('actor.email'))->toBeNull();

    $legacy = AuditTrail::query()->find($record->id);
    expect($legacy)->not->toBeNull()
        ->and($legacy->user_id)->toBeNull()
        ->and($legacy->user)->toBeNull();
});

test('RecordAuditTrail falls back to authenticated user when actor is not passed', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::UserActivated,
    );

    expect($record)->not->toBeNull();
    assert($record instanceof Activity);

    expect($record->causer_id)->toBe($user->id)
        ->and($record->causer?->id)->toBe($user->id);

    $legacy = AuditTrail::query()->find($record->id);
    expect($legacy)->not->toBeNull()
        ->and($legacy->user_id)->toBe($user->id);
});

test('keeps the actor identity after the actor is deleted', function (): void {
    $actor = User::factory()->create(['name' => 'Ada', 'email' => 'ada@example.com']);
    $this->actingAs($actor);

    $record = resolve(RecordAuditTrail::class)->handle(event: AuditEvent::SettingsUpdated);
    expect($record)->not->toBeNull();
    assert($record instanceof Activity);

    $actor->delete();

    $record->refresh();
    expect($record->causer)->toBeNull()
        ->and($record->getProperty('actor.email'))->toBe('ada@example.com')
        ->and($record->getProperty('actor.name'))->toBe('Ada');

    $legacy = AuditTrail::query()->find($record->id);
    expect($legacy)->not->toBeNull()
        ->and($legacy->user_id)->toBeNull()
        ->and($legacy->actor_email)->toBe('ada@example.com')
        ->and($legacy->actor_name)->toBe('Ada');
});

test('RecordAuditTrail respects activitylog.enabled configuration', function (): void {
    config()->set('activitylog.enabled', false);

    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['key' => 'old'],
        newValues: ['key' => 'new'],
    );

    expect($record)->toBeNull()
        ->and(Activity::query()->count())->toBe(0)
        ->and(AuditTrail::query()->count())->toBe(0);
});

test('RecordAuditTrail handles custom auditableType and auditableId strings', function (): void {
    $action = resolve(RecordAuditTrail::class);
    $uuid = (string) Str::uuid();

    $record = $action->handle(
        event: AuditEvent::UserCreated,
        auditableType: User::class,
        auditableId: $uuid,
    );

    expect($record)->not->toBeNull();
    assert($record instanceof Activity);

    expect($record->subject_type)->toBe(User::class)
        ->and($record->subject_id)->toBe($uuid);

    $legacy = AuditTrail::query()->find($record->id);
    expect($legacy)->not->toBeNull()
        ->and($legacy->auditable_type)->toBe(User::class)
        ->and($legacy->auditable_id)->toBe($uuid);
});

test('RecordAuditTrail handles console execution without bound request', function (): void {
    app()->forgetInstance('request');
    $action = new RecordAuditTrail();

    $record = $action->handle(
        event: AuditEvent::SettingsUpdated,
    );

    expect($record)->not->toBeNull();
    assert($record instanceof Activity);

    expect($record->ip_address)->toBeNull()
        ->and($record->user_agent)->toBeNull()
        ->and($record->getProperty('request.url'))->toBeNull();
});

test('RecordAuditTrail creates exactly one activity log record per invocation without duplication', function (): void {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $action = resolve(RecordAuditTrail::class);

    $initialActivityCount = Activity::query()->count();
    $initialAuditTrailCount = AuditTrail::query()->count();

    $record = $action->handle(
        event: AuditEvent::UserUpdated,
        auditable: $target,
        oldValues: ['name' => 'Initial'],
        newValues: ['name' => 'Updated'],
        user: $actor,
    );

    expect($record)->not->toBeNull()
        ->and(Activity::query()->count())->toBe($initialActivityCount + 1)
        ->and(AuditTrail::query()->count())->toBe($initialAuditTrailCount + 1);
});

test('activity model hides properties and attribute_changes upon serialization', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $record = resolve(RecordAuditTrail::class)->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['secret_token' => 'topsecret'],
        newValues: ['secret_token' => 'newsecret'],
    );

    expect($record)->not->toBeNull();
    assert($record instanceof Activity);

    $serialized = $record->toArray();
    expect($serialized)->not->toHaveKey('properties')
        ->and($serialized)->not->toHaveKey('attribute_changes');
});
