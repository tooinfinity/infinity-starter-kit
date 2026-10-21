<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditTrail;
use App\Models\User;

test('audit trail can be created and casts attributes correctly', function (): void {
    $user = User::factory()->create();

    $trail = AuditTrail::factory()->create([
        'user_id' => $user->id,
        'actor_name' => 'Alice Auditor',
        'actor_email' => 'alice@example.com',
        'event' => AuditEvent::UserCreated,
        'auditable_type' => $user->getMorphClass(),
        'auditable_id' => $user->id,
        'old_values' => ['name' => 'Old Name'],
        'new_values' => ['name' => 'New Name'],
        'tags' => ['auth', 'users'],
    ]);

    expect($trail->id)->toBeString()
        ->and($trail->user_id)->toBe($user->id)
        ->and($trail->actor_name)->toBe('Alice Auditor')
        ->and($trail->actor_email)->toBe('alice@example.com')
        ->and($trail->event)->toBe(AuditEvent::UserCreated)
        ->and($trail->old_values)->toBe(['name' => 'Old Name'])
        ->and($trail->new_values)->toBe(['name' => 'New Name'])
        ->and($trail->tags)->toBe(['auth', 'users'])
        ->and($trail->created_at)->not->toBeNull();
});

test('audit trail relationships resolve correctly', function (): void {
    $user = User::factory()->create();

    $trail = AuditTrail::factory()->create([
        'user_id' => $user->id,
        'auditable_type' => $user->getMorphClass(),
        'auditable_id' => $user->id,
    ]);

    expect($trail->user)->toBeInstanceOf(User::class)
        ->and($trail->user->id)->toBe($user->id)
        ->and($trail->auditable)->toBeInstanceOf(User::class)
        ->and($trail->auditable->id)->toBe($user->id);
});
