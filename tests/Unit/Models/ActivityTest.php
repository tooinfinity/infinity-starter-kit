<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use RuntimeException;

test('activity model adheres to HasUuids contract', function (): void {
    $traits = class_uses_recursive(Activity::class);

    expect(in_array(HasUuids::class, $traits, true))->toBeTrue();
});

test('activity model declares null updated_at and immutability handlers', function (): void {
    expect(Activity::UPDATED_AT)->toBeNull();

    $dispatcher = Activity::getEventDispatcher();
    expect($dispatcher)->not->toBeNull();

    $activity = new Activity;

    expect(fn () => $dispatcher->until('eloquent.updating: '.Activity::class, $activity))
        ->toThrow(RuntimeException::class, 'Audit trail records are immutable and cannot be updated.');

    expect(fn () => $dispatcher->until('eloquent.deleting: '.Activity::class, $activity))
        ->toThrow(RuntimeException::class, 'Audit trail records cannot be individually deleted. Use the retention prune command instead.');
});

test('activity model correctly maps and casts legacy attributes', function (): void {
    $user = User::factory()->create();

    $activity = Activity::query()->create([
        'user_id' => $user->id,
        'actor_name' => 'Alice Auditor',
        'actor_email' => 'alice@example.com',
        'event' => AuditEvent::UserCreated,
        'auditable_type' => $user->getMorphClass(),
        'auditable_id' => $user->id,
        'old_values' => ['name' => 'Old Name'],
        'new_values' => ['name' => 'New Name'],
        'url' => 'http://localhost/test',
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestAgent/1.0',
        'tags' => ['users', 'auth'],
    ]);

    expect($activity->id)->toBeString()
        ->and($activity->user_id)->toBe($user->id)
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->actor_name)->toBe('Alice Auditor')
        ->and($activity->actor_email)->toBe('alice@example.com')
        ->and($activity->event)->toBe(AuditEvent::UserCreated)
        ->and($activity->auditable_type)->toBe($user->getMorphClass())
        ->and($activity->auditable_id)->toBe($user->id)
        ->and($activity->old_values)->toBe(['name' => 'Old Name'])
        ->and($activity->new_values)->toBe(['name' => 'New Name'])
        ->and($activity->url)->toBe('http://localhost/test')
        ->and($activity->ip_address)->toBe('127.0.0.1')
        ->and($activity->user_agent)->toBe('TestAgent/1.0')
        ->and($activity->tags)->toBe(['users', 'auth'])
        ->and($activity->user)->toBeInstanceOf(User::class)
        ->and($activity->auditable)->toBeInstanceOf(User::class);
});

test('activity model handles non-enum string events gracefully', function (): void {
    $activity = Activity::query()->create([
        'event' => 'custom_spatie_event',
    ]);

    expect($activity->event)->toBe('custom_spatie_event');

    $refreshed = Activity::query()->findOrFail($activity->id);
    expect($refreshed->event)->toBe('custom_spatie_event');

    $nullEventActivity = Activity::factory()->anonymous()->create([
        'event' => null,
    ]);
    expect($nullEventActivity->event)->toBeNull();
});

test('activity model supports two-argument where query semantics and eager loading', function (): void {
    $user = User::factory()->create();

    $activity = Activity::query()->create([
        'user_id' => $user->id,
        'actor_name' => 'Bob Reviewer',
        'auditable_type' => $user->getMorphClass(),
        'auditable_id' => $user->id,
    ]);

    $queriedByActor = Activity::query()->where('actor_name', 'Bob Reviewer')->first();
    expect($queriedByActor)->not->toBeNull()
        ->and($queriedByActor->id)->toBe($activity->id);

    $queriedByUserId = Activity::query()->where('user_id', $user->id)->first();
    expect($queriedByUserId)->not->toBeNull()
        ->and($queriedByUserId->id)->toBe($activity->id);

    $eagerLoaded = Activity::query()
        ->with(['user', 'auditable'])
        ->find($activity->id);

    expect($eagerLoaded)->not->toBeNull()
        ->and($eagerLoaded->relationLoaded('user'))->toBeTrue()
        ->and($eagerLoaded->relationLoaded('auditable'))->toBeTrue()
        ->and($eagerLoaded->user)->toBeInstanceOf(User::class)
        ->and($eagerLoaded->auditable)->toBeInstanceOf(User::class);
});

test('activity model translates user_id to causer_id for array-form predicates', function (): void {
    $user = User::factory()->create();

    $activity = Activity::query()->create([
        'user_id' => $user->id,
        'actor_name' => 'Charlie Array',
    ]);

    $associativeQuery = Activity::query()->where(['user_id' => $user->id])->first();
    expect($associativeQuery)->not->toBeNull()
        ->and($associativeQuery->id)->toBe($activity->id);

    $tupleQuery = Activity::query()->where([['user_id', '=', $user->id]])->first();
    expect($tupleQuery)->not->toBeNull()
        ->and($tupleQuery->id)->toBe($activity->id);
});

test('activity model returns null for missing old_values and new_values and supports 4-argument where', function (): void {
    $activity = Activity::query()->create(['auditable_id' => 'test-target-id']);

    expect($activity->old_values)->toBeNull()
        ->and($activity->new_values)->toBeNull();

    $queried = Activity::query()
        ->where('auditable_id', '=', 'test-target-id', 'and')
        ->where(fn ($query) => $query->where('auditable_id', 'test-target-id'))
        ->first();

    expect($queried)->not->toBeNull()
        ->and($queried->id)->toBe($activity->id);

    $activity->old_values = null;
    $activity->new_values = null;

    expect($activity->old_values)->toBeNull()
        ->and($activity->new_values)->toBeNull();
});
