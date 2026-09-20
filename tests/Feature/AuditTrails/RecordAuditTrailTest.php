<?php

declare(strict_types=1);

use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Http\Request;

/* @chisel-audit-trails */

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

    expect($record)->toBeInstanceOf(AuditTrail::class)
        ->and($record->user_id)->toBe($actor->id)
        ->and($record->event)->toBe(AuditEvent::UserUpdated)
        ->and($record->auditable_type)->toBe($target->getMorphClass())
        ->and($record->auditable_id)->toBe($target->id)
        ->and($record->old_values)->toBe(['name' => 'Old Name'])
        ->and($record->new_values)->toBe(['name' => 'New Name'])
        ->and($record->url)->toBe('http://localhost/users/'.$target->id)
        ->and($record->ip_address)->toBe('192.168.1.100')
        ->and($record->user_agent)->toBe('TestBrowser/1.0')
        ->and($record->tags)->toBe(['users', 'management'])
        ->and($record->created_at)->not->toBeNull();
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
            'two_factor_secret' => '2fa_secret_key',
            'two_factor_recovery_codes' => ['code1', 'code2'],
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

    expect($record->old_values['password'])->toBe('[REDACTED]')
        ->and($record->old_values['password_confirmation'])->toBe('[REDACTED]')
        ->and($record->old_values['current_password'])->toBe('[REDACTED]')
        ->and($record->old_values['remember_token'])->toBe('[REDACTED]')
        ->and($record->old_values['two_factor_secret'])->toBe('[REDACTED]')
        ->and($record->old_values['two_factor_recovery_codes'])->toBe('[REDACTED]')
        ->and($record->old_values['api_key'])->toBe('[REDACTED]')
        ->and($record->old_values['app_token'])->toBe('[REDACTED]')
        ->and($record->old_values['safe_field'])->toBe('visible_value')
        ->and($record->new_values['password'])->toBe('[REDACTED]')
        ->and($record->new_values['nested']['password'])->toBe('[REDACTED]')
        ->and($record->new_values['nested']['normal_key'])->toBe('normal_val')
        ->and($record->new_values['safe_field'])->toBe('new_visible_value');
});

test('RecordAuditTrail handles anonymous or system actions without an actor', function (): void {
    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['app.name' => 'Old'],
        newValues: ['app.name' => 'New'],
    );

    expect($record->user_id)->toBeNull()
        ->and($record->user)->toBeNull();
});

test('RecordAuditTrail falls back to authenticated user when actor is not passed', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $action = resolve(RecordAuditTrail::class);

    $record = $action->handle(
        event: AuditEvent::UserActivated,
    );

    expect($record->user_id)->toBe($user->id);
});

/* @end-chisel-audit-trails */
