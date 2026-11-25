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
    $r1 = $recorder->handle(
        event: AuditEvent::SettingsUpdated,
        oldValues: ['site_name' => 'Old Name'],
        newValues: ['site_name' => 'New Name'],
        user: $user,
        tags: ['settings'],
    );

    // 2. User updated their own profile
    $r2 = $recorder->handle(
        event: AuditEvent::UserUpdated,
        auditable: $user,
        oldValues: ['name' => 'Alice Actor', 'email' => 'alice@example.com', 'is_active' => true],
        newValues: ['name' => 'Alice Updated', 'email' => 'alice_updated@example.com', 'is_active' => true],
        user: $user,
        tags: ['users'],
    );

    $action = resolve(UpdateUserAuditTrails::class);
    $action->handle($user);

    $records = Activity::query()->whereIn('id', [$r1->id, $r2->id])->get();
    expect($records)->toHaveCount(2);

    foreach ($records as $record) {
        expect($record->actor_name)->toBeNull()
            ->and($record->actor_email)->toBeNull()
            ->and($record->ip_address)->toBeNull()
            ->and($record->user_agent)->toBeNull()
            ->and($record->tags)->toContain('anonymized');
    }

    $raw1 = DB::table('activity_log')->where('id', $r1->id)->first();
    expect($raw1)->not->toBeNull()
        ->and($raw1->causer_id)->toBeNull()
        ->and($raw1->causer_type)->toBeNull()
        ->and($raw1->ip_address)->toBeNull()
        ->and($raw1->user_agent)->toBeNull();
    $raw1Props = json_decode((string) $raw1->properties, true);
    expect($raw1Props['actor']['name'])->toBeNull()
        ->and($raw1Props['actor']['email'])->toBeNull()
        ->and($raw1Props['tags'])->toContain('anonymized');

    $raw2 = DB::table('activity_log')->where('id', $r2->id)->first();
    expect($raw2)->not->toBeNull()
        ->and($raw2->causer_id)->toBeNull()
        ->and($raw2->causer_type)->toBeNull()
        ->and($raw2->subject_id)->toBe($user->id)
        ->and($raw2->subject_type)->toBe($user->getMorphClass())
        ->and($raw2->ip_address)->toBeNull()
        ->and($raw2->user_agent)->toBeNull();
    $raw2Props = json_decode((string) $raw2->properties, true);
    expect($raw2Props['actor']['name'])->toBeNull()
        ->and($raw2Props['actor']['email'])->toBeNull()
        ->and($raw2Props['tags'])->toContain('anonymized');

    $updatedRecord = Activity::query()->where('event', AuditEvent::UserUpdated->value)->first();
    expect($updatedRecord)->not->toBeNull()
        ->and($updatedRecord->old_values)->not->toHaveKey('name')
        ->and($updatedRecord->old_values)->not->toHaveKey('email')
        ->and($updatedRecord->old_values['is_active'])->toBeTrue()
        ->and($updatedRecord->new_values)->not->toHaveKey('name')
        ->and($updatedRecord->new_values)->not->toHaveKey('email')
        ->and($updatedRecord->new_values['is_active'])->toBeTrue();

    $updatedActivity = Activity::query()->where('event', AuditEvent::UserUpdated->value)->first();
    expect($updatedActivity)->not->toBeNull()
        ->and($updatedActivity->attribute_changes?->get('old'))->not->toHaveKey('name')
        ->and($updatedActivity->attribute_changes?->get('old'))->not->toHaveKey('email')
        ->and($updatedActivity->attribute_changes?->get('old')['is_active'])->toBeTrue()
        ->and($updatedActivity->attribute_changes?->get('attributes'))->not->toHaveKey('name')
        ->and($updatedActivity->attribute_changes?->get('attributes'))->not->toHaveKey('email')
        ->and($updatedActivity->attribute_changes?->get('attributes')['is_active'])->toBeTrue();
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
        ->and($createdRecord->user_id)->toBe($admin->id)
        ->and($createdRecord->actor_name)->toBe('Admin Boss')
        ->and($createdRecord->actor_email)->toBe('admin@example.com')
        ->and($createdRecord->tags)->toContain('anonymized')
        ->and($createdRecord->new_values)->not->toHaveKey('name')
        ->and($createdRecord->new_values)->not->toHaveKey('email')
        ->and($createdRecord->new_values['is_active'])->toBeTrue();

    $createdActivity = Activity::query()->where('event', AuditEvent::UserCreated->value)->first();
    expect($createdActivity)->not->toBeNull()
        ->and($createdActivity->causer_id)->toBe($admin->id)
        ->and($createdActivity->getProperty('actor.name'))->toBe('Admin Boss')
        ->and($createdActivity->getProperty('actor.email'))->toBe('admin@example.com')
        ->and($createdActivity->getProperty('tags'))->toContain('anonymized')
        ->and($createdActivity->attribute_changes?->get('attributes'))->not->toHaveKey('name')
        ->and($createdActivity->attribute_changes?->get('attributes'))->not->toHaveKey('email')
        ->and($createdActivity->attribute_changes?->get('attributes')['is_active'])->toBeTrue();

    $rawCreated = DB::table('activity_log')->where('id', $createdRecord->id)->first();
    expect($rawCreated)->not->toBeNull()
        ->and($rawCreated->causer_id)->toBe($admin->id)
        ->and($rawCreated->causer_type)->toBe($admin->getMorphClass())
        ->and($rawCreated->subject_id)->toBe($targetUser->id)
        ->and($rawCreated->subject_type)->toBe($targetUser->getMorphClass());
    $rawCreatedProps = json_decode((string) $rawCreated->properties, true);
    expect($rawCreatedProps['actor']['name'])->toBe('Admin Boss')
        ->and($rawCreatedProps['actor']['email'])->toBe('admin@example.com')
        ->and($rawCreatedProps['tags'])->toContain('anonymized');
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
        ->and($record->actor_name)->toBe('Charlie User')
        ->and($record->actor_email)->toBe('charlie@example.com')
        ->and($record->tags)->not->toContain('anonymized');

    $activity = Activity::query()->where('causer_id', $user->id)->first();
    expect($activity)->not->toBeNull()
        ->and($activity->getProperty('actor.name'))->toBe('Charlie User')
        ->and($activity->getProperty('actor.email'))->toBe('charlie@example.com')
        ->and($activity->getProperty('tags'))->not->toContain('anonymized');
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
        ->and($record->actor_name)->toBe('Diana User')
        ->and($record->actor_email)->toBe('diana@example.com')
        ->and($record->tags)->not->toContain('anonymized');

    $activity = Activity::query()->where('causer_id', $user->id)->first();
    expect($activity)->not->toBeNull()
        ->and($activity->getProperty('actor.name'))->toBe('Diana User')
        ->and($activity->getProperty('actor.email'))->toBe('diana@example.com')
        ->and($activity->getProperty('tags'))->not->toContain('anonymized');
});

test('UpdateUserAuditTrails handles activity log records with null properties, null changes, or properties changes', function (): void {
    $user = User::factory()->create([
        'name' => 'Eve User',
        'email' => 'eve@example.com',
    ]);

    $id1 = (string) Str::uuid();
    $id2 = (string) Str::uuid();

    // 1. Activity record with null properties and null attribute_changes
    DB::table('activity_log')->insert([
        'id' => $id1,
        'log_name' => 'default',
        'description' => 'Test nulls',
        'event' => 'custom.event',
        'causer_id' => $user->id,
        'causer_type' => $user->getMorphClass(),
        'properties' => null,
        'attribute_changes' => null,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Browser',
        'created_at' => now(),
    ]);

    // 2. Activity record where changes are stored in properties['old'] and properties['attributes'] (no tags)
    DB::table('activity_log')->insert([
        'id' => $id2,
        'log_name' => 'default',
        'description' => 'Test properties changes',
        'event' => 'custom.event',
        'subject_id' => $user->id,
        'subject_type' => $user->getMorphClass(),
        'properties' => json_encode([
            'old' => ['name' => 'Eve User', 'status' => 'active'],
            'attributes' => ['name' => 'Eve New', 'status' => 'active'],
        ], JSON_THROW_ON_ERROR),
        'attribute_changes' => null,
        'created_at' => now(),
    ]);

    $action = resolve(UpdateUserAuditTrails::class);
    $action->handle($user);

    $raw1 = DB::table('activity_log')->where('id', $id1)->first();
    expect($raw1)->not->toBeNull()
        ->and($raw1->causer_id)->toBeNull()
        ->and($raw1->causer_type)->toBeNull()
        ->and($raw1->ip_address)->toBeNull()
        ->and($raw1->user_agent)->toBeNull();
    $raw1Props = json_decode((string) $raw1->properties, true);
    expect($raw1Props['tags'])->toBe(['anonymized']);

    $raw2 = DB::table('activity_log')->where('id', $id2)->first();
    expect($raw2)->not->toBeNull()
        ->and($raw2->subject_id)->toBe($user->id);
    $raw2Props = json_decode((string) $raw2->properties, true);
    expect($raw2Props['old'])->not->toHaveKey('name')
        ->and($raw2Props['old']['status'])->toBe('active')
        ->and($raw2Props['attributes'])->not->toHaveKey('name')
        ->and($raw2Props['attributes']['status'])->toBe('active')
        ->and($raw2Props['tags'])->toBe(['anonymized']);
});
