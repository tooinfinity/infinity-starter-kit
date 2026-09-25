<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditTrail;
use App\Models\Setting;
use App\Models\User;
use App\Queries\Reporting\AuditReportQuery;
use Illuminate\Support\Facades\Date;

test('audit report query handles empty dataset gracefully', function (): void {
    AuditTrail::query()->delete();

    $query = new AuditReportQuery;
    $result = $query->handle([
        'date_from' => Date::now()->subDays(5)->toDateString(),
        'date_to' => Date::now()->toDateString(),
    ]);

    expect($result['summary'])->toHaveCount(4)
        ->and($result['summary'][0]->value)->toBe(0)
        ->and($result['summary'][1]->value)->toBe(0)
        ->and($result['summary'][2]->value)->toBe(__('reports.common.none'))
        ->and($result['summary'][3]->value)->toBe(__('reports.common.none'))
        ->and($result['series'])->toHaveCount(6)
        ->and($result['breakdown'])->toBeEmpty()
        ->and($result['paginated']->total())->toBe(0);
});

test('audit report query computes accurate summary metrics and event breakdown', function (): void {
    AuditTrail::query()->delete();

    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $baseDate = Date::now()->subDays(2);

    AuditTrail::factory()->create([
        'user_id' => $user1->id,
        'event' => AuditEvent::UserCreated,
        'auditable_type' => User::class,
        'auditable_id' => $user1->id,
        'created_at' => $baseDate->copy()->startOfDay()->addHours(1),
    ]);

    AuditTrail::factory()->create([
        'user_id' => $user1->id,
        'event' => AuditEvent::UserCreated,
        'auditable_type' => User::class,
        'auditable_id' => $user2->id,
        'created_at' => $baseDate->copy()->addDays(1)->startOfDay()->addHours(2),
    ]);

    AuditTrail::factory()->create([
        'user_id' => $user2->id,
        'event' => AuditEvent::UserUpdated,
        'auditable_type' => User::class,
        'auditable_id' => $user1->id,
        'created_at' => $baseDate->copy()->addDays(1)->startOfDay()->addHours(5),
    ]);

    // System audit event (user_id is null)
    AuditTrail::factory()->create([
        'user_id' => null,
        'event' => AuditEvent::SettingsUpdated,
        'auditable_type' => Setting::class,
        'auditable_id' => '1',
        'created_at' => $baseDate->copy()->addDays(1)->startOfDay()->addHours(6),
    ]);

    $query = new AuditReportQuery;
    $result = $query->handle([
        'date_from' => $baseDate->toDateString(),
        'date_to' => $baseDate->copy()->addDays(1)->toDateString(),
    ]);

    // Total: 4, Unique actors: 2 (user1 and user2)
    expect($result['summary'][0]->value)->toBe(4)
        ->and($result['summary'][1]->value)->toBe(2)
        ->and($result['summary'][2]->value)->toBe(AuditEvent::UserCreated->label())
        ->and($result['summary'][3]->value)->toBe('User')
        ->and($result['paginated']->total())->toBe(4);

    // Breakdown
    expect($result['breakdown'])->toHaveCount(3);
    $userCreatedBreakdown = collect($result['breakdown'])->firstWhere('key', AuditEvent::UserCreated->value);
    expect($userCreatedBreakdown?->count)->toBe(2)
        ->and($userCreatedBreakdown?->percentage)->toBe(50.0);
});

test('audit report query filters by event, user_id and search', function (): void {
    AuditTrail::query()->delete();

    $user = User::factory()->create(['name' => 'Specific Actor', 'email' => 'actor@example.com']);
    $otherUser = User::factory()->create(['name' => 'Other Person', 'email' => 'other@example.com']);

    $today = Date::now();

    AuditTrail::factory()->create([
        'user_id' => $user->id,
        'event' => AuditEvent::UserCreated,
        'ip_address' => '192.168.1.100',
        'created_at' => $today,
    ]);

    AuditTrail::factory()->create([
        'user_id' => $otherUser->id,
        'event' => AuditEvent::UserDeleted,
        'ip_address' => '10.0.0.1',
        'created_at' => $today,
    ]);

    $query = new AuditReportQuery;

    // Filter by event
    $eventResult = $query->handle([
        'date_from' => $today->copy()->subDay()->toDateString(),
        'date_to' => $today->copy()->addDay()->toDateString(),
        'event' => AuditEvent::UserCreated->value,
    ]);
    expect($eventResult['paginated']->total())->toBe(1)
        ->and($eventResult['paginated']->first()?->event)->toBe(AuditEvent::UserCreated);

    // Filter by user_id
    $userResult = $query->handle([
        'date_from' => $today->copy()->subDay()->toDateString(),
        'date_to' => $today->copy()->addDay()->toDateString(),
        'user_id' => $user->id,
    ]);
    expect($userResult['paginated']->total())->toBe(1)
        ->and($userResult['paginated']->first()?->user_id)->toBe($user->id);

    // Search by IP
    $ipResult = $query->handle([
        'date_from' => $today->copy()->subDay()->toDateString(),
        'date_to' => $today->copy()->addDay()->toDateString(),
        'search' => '192.168.1.100',
    ]);
    expect($ipResult['paginated']->total())->toBe(1)
        ->and($ipResult['paginated']->first()?->ip_address)->toBe('192.168.1.100');

    // Search by user name
    $actorResult = $query->handle([
        'date_from' => $today->copy()->subDay()->toDateString(),
        'date_to' => $today->copy()->addDay()->toDateString(),
        'search' => 'Specific Actor',
    ]);
    expect($actorResult['paginated']->total())->toBe(1);
});
