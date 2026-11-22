<?php

declare(strict_types=1);

use App\Actions\AuditTrails\RecordAuditTrail;
use App\Actions\AuditTrails\UpdateUserAuditTrails;
use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('UpdateUserAuditTrails anonymizes actor fields and scrubs PII from user audit trails', function (): void {
    $user = User::factory()->create([
        'name' => 'Alice Actor',
        'email' => 'alice@example.com',
    ]);

    $recorder = resolve(RecordAuditTrail::class);

    // 1. User performed an action as actor
    $recorder->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['site_name' => 'Old Name'],
        newValues: ['site_name' => 'New Name'],
        user: $user,
        tags: ['settings'],
    );

    // 2. User updated their own profile
    $recorder->handle(
        event: AuditEvent::UserUpdated,
        auditable: $user,
        oldValues: ['name' => 'Alice Actor', 'email' => 'alice@example.com', 'is_active' => true],
        newValues: ['name' => 'Alice Updated', 'email' => 'alice_updated@example.com', 'is_active' => true],
        user: $user,
        tags: ['users'],
    );

    $action = resolve(UpdateUserAuditTrails::class);
    $action->handle($user);

    $records = Activity::query()->where('causer_id', $user->id)->get();
    expect($records)->toHaveCount(2);

    foreach ($records as $record) {
        expect($record->getProperty('actor.name'))->toBeNull()
            ->and($record->getProperty('actor.email'))->toBeNull()
            ->and($record->ip_address)->toBeNull()
            ->and($record->user_agent)->toBeNull()
            ->and($record->getProperty('tags'))->toContain('anonymized');
    }

    $updatedRecord = Activity::query()->where('event', AuditEvent::UserUpdated->value)->first();
    expect($updatedRecord)->not->toBeNull()
        ->and($updatedRecord->attribute_changes?->get('old'))->not->toHaveKey('name')
        ->and($updatedRecord->attribute_changes?->get('old'))->not->toHaveKey('email')
        ->and($updatedRecord->attribute_changes?->get('old')['is_active'])->toBeTrue()
        ->and($updatedRecord->attribute_changes?->get('attributes'))->not->toHaveKey('name')
        ->and($updatedRecord->attribute_changes?->get('attributes'))->not->toHaveKey('email')
        ->and($updatedRecord->attribute_changes?->get('attributes')['is_active'])->toBeTrue();
});

test('UpdateUserAuditTrails scrubs target PII while preserving admin actor details', function (): void {
    $admin = User::factory()->create([
        'name' => 'Admin Boss',
        'email' => 'admin@example.com',
    ]);
    $targetUser = User::factory()->create([
        'name' => 'Bob Target',
        'email' => 'bob@example.com',
    ]);

    $recorder = resolve(RecordAuditTrail::class);

    // Admin created target user
    $recorder->handle(
        event: AuditEvent::UserCreated,
        auditable: $targetUser,
        newValues: ['name' => 'Bob Target', 'email' => 'bob@example.com', 'is_active' => true],
        user: $admin,
        tags: ['users'],
    );

    $action = resolve(UpdateUserAuditTrails::class);
    $action->handle($targetUser);

    $createdRecord = Activity::query()->where('event', AuditEvent::UserCreated->value)->first();
    expect($createdRecord)->not->toBeNull()
        ->and($createdRecord->causer_id)->toBe($admin->id)
        ->and($createdRecord->getProperty('actor.name'))->toBe('Admin Boss')
        ->and($createdRecord->getProperty('actor.email'))->toBe('admin@example.com')
        ->and($createdRecord->getProperty('tags'))->toContain('anonymized')
        ->and($createdRecord->attribute_changes?->get('attributes'))->not->toHaveKey('name')
        ->and($createdRecord->attribute_changes?->get('attributes'))->not->toHaveKey('email')
        ->and($createdRecord->attribute_changes?->get('attributes')['is_active'])->toBeTrue();
});

test('UpdateUserAuditTrails respects disabled anonymize_on_delete configuration', function (): void {
    config()->set('audit.anonymize_on_delete', false);

    $user = User::factory()->create([
        'name' => 'Charlie User',
        'email' => 'charlie@example.com',
    ]);

    $recorder = resolve(RecordAuditTrail::class);
    $recorder->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['name' => 'Old App'],
        user: $user,
        tags: ['settings'],
    );

    $action = resolve(UpdateUserAuditTrails::class);
    $action->handle($user);

    $record = Activity::query()->where('causer_id', $user->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->getProperty('actor.name'))->toBe('Charlie User')
        ->and($record->getProperty('actor.email'))->toBe('charlie@example.com')
        ->and($record->getProperty('tags'))->not->toContain('anonymized');
});

test('UpdateUserAuditTrails allows explicit anonymize parameter override', function (): void {
    config()->set('audit.anonymize_on_delete', true);

    $user = User::factory()->create([
        'name' => 'Diana User',
        'email' => 'diana@example.com',
    ]);

    $recorder = resolve(RecordAuditTrail::class);
    $recorder->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['name' => 'Old App'],
        user: $user,
        tags: ['settings'],
    );

    $action = resolve(UpdateUserAuditTrails::class);
    $action->handle($user, anonymize: false);

    $record = Activity::query()->where('causer_id', $user->id)->first();
    expect($record)->not->toBeNull()
        ->and($record->getProperty('actor.name'))->toBe('Diana User')
        ->and($record->getProperty('actor.email'))->toBe('diana@example.com')
        ->and($record->getProperty('tags'))->not->toContain('anonymized');
});

test('UpdateUserAuditTrails handles null properties and recursively scrubs nested arrays', function (): void {
    $user = User::factory()->create([
        'name' => 'Eve User',
        'email' => 'eve@example.com',
    ]);

    $recorder = resolve(RecordAuditTrail::class);
    $recorded = $recorder->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: [
            'meta' => [
                'name' => 'Eve Nested',
                'team' => 'Engineering',
            ],
        ],
        user: $user,
    );

    $nullId = (string) Str::uuid();
    DB::table('activity_log')->insert([
        'id' => $nullId,
        'causer_type' => User::class,
        'causer_id' => $user->id,
        'properties' => null,
        'created_at' => now(),
    ]);

    $action = resolve(UpdateUserAuditTrails::class);
    $action->handle($user);

    $scrubbed = Activity::query()->where('id', $recorded->id)->first();

    expect($scrubbed)->not->toBeNull()
        ->and($scrubbed->attribute_changes?->get('old')['meta'])->toBe(['team' => 'Engineering']);

    $nullRecord = Activity::query()->where('id', $nullId)->first();
    expect($nullRecord)->not->toBeNull()
        ->and($nullRecord->getProperty('tags'))->toContain('anonymized')
        ->and($nullRecord->attribute_changes)->toBeNull();
});

test('UpdateUserAuditTrails scrubs both properties and attribute_changes for real user update', function (): void {
    $user = User::factory()->create([
        'name' => 'Frank User',
        'email' => 'frank@example.com',
    ]);

    $activity = Activity::factory()->create([
        'subject_type' => User::class,
        'subject_id' => $user->id,
        'causer_type' => User::class,
        'causer_id' => $user->id,
        'event' => AuditEvent::UserUpdated->value,
        'attribute_changes' => [
            'old' => ['name' => 'Frank User', 'email' => 'frank@example.com', 'role' => 'user'],
            'attributes' => ['name' => 'Frank Updated', 'email' => 'frank_new@example.com', 'role' => 'user'],
        ],
    ]);

    $action = resolve(UpdateUserAuditTrails::class);
    $action->handle($user);

    $reloaded = Activity::query()->where('id', $activity->id)->first();
    expect($reloaded)->not->toBeNull()
        ->and($reloaded->attribute_changes?->get('old'))->not->toHaveKey('name')
        ->and($reloaded->attribute_changes?->get('old'))->not->toHaveKey('email')
        ->and($reloaded->attribute_changes?->get('old')['role'])->toBe('user')
        ->and($reloaded->attribute_changes?->get('attributes'))->not->toHaveKey('name')
        ->and($reloaded->attribute_changes?->get('attributes'))->not->toHaveKey('email')
        ->and($reloaded->attribute_changes?->get('attributes')['role'])->toBe('user');
});
