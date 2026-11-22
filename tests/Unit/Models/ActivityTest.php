<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Collection;
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

test('activity model persists and casts native attributes and relations', function (): void {
    $user = User::factory()->create();

    $activity = Activity::query()->create([
        'log_name' => 'users',
        'description' => AuditEvent::UserCreated->value,
        'event' => AuditEvent::UserCreated->value,
        'causer_type' => $user->getMorphClass(),
        'causer_id' => $user->id,
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->id,
        'attribute_changes' => [
            'old' => ['name' => 'Old Name'],
            'attributes' => ['name' => 'New Name'],
        ],
        'properties' => [
            'actor' => [
                'name' => 'Alice Auditor',
                'email' => 'alice@example.com',
            ],
            'request' => [
                'url' => 'http://localhost/test',
            ],
            'tags' => ['users', 'auth'],
        ],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestAgent/1.0',
    ]);

    expect($activity->id)->toBeString()
        ->and($activity->log_name)->toBe('users')
        ->and($activity->description)->toBe(AuditEvent::UserCreated->value)
        ->and($activity->event)->toBe(AuditEvent::UserCreated->value)
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($user->id)
        ->and($activity->ip_address)->toBe('127.0.0.1')
        ->and($activity->user_agent)->toBe('TestAgent/1.0')
        ->and($activity->attribute_changes)->toBeInstanceOf(Collection::class)
        ->and($activity->attribute_changes?->get('old'))->toBe(['name' => 'Old Name'])
        ->and($activity->attribute_changes?->get('attributes'))->toBe(['name' => 'New Name'])
        ->and($activity->properties)->toBeInstanceOf(Collection::class)
        ->and($activity->getProperty('actor.name'))->toBe('Alice Auditor')
        ->and($activity->getProperty('actor.email'))->toBe('alice@example.com')
        ->and($activity->getProperty('tags'))->toBe(['users', 'auth'])
        ->and($activity->causer)->toBeInstanceOf(User::class)
        ->and($activity->subject)->toBeInstanceOf(User::class);
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

test('activity model supports spatie query scopes', function (): void {
    $user = User::factory()->create();

    $activity = Activity::query()->create([
        'log_name' => 'settings',
        'event' => AuditEvent::SettingsUpdated->value,
        'description' => AuditEvent::SettingsUpdated->value,
        'causer_type' => $user->getMorphClass(),
        'causer_id' => $user->id,
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->id,
    ]);

    expect(Activity::query()->inLog('settings')->where('id', $activity->id)->exists())->toBeTrue()
        ->and(Activity::query()->causedBy($user)->where('id', $activity->id)->exists())->toBeTrue()
        ->and(Activity::query()->forSubject($user)->where('id', $activity->id)->exists())->toBeTrue()
        ->and(Activity::query()->forEvent(AuditEvent::SettingsUpdated->value)->where('id', $activity->id)->exists())->toBeTrue();
});
