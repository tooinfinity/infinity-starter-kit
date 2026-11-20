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
