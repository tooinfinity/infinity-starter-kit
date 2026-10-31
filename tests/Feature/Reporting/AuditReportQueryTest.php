<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Models\AuditTrail;
use App\Models\User;
use App\Queries\Reporting\AuditReportQuery;
use App\Queries\Reporting\ExportAuditReportStream;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        'event' => AuditEvent::UserDeleted,
        'auditable_type' => User::class,
        'auditable_id' => $user1->id,
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

it('buckets report days in the configured display timezone', function (): void {
    config(['app.display_timezone' => 'Africa/Algiers']); // UTC+1

    AuditTrail::query()->delete();

    AuditTrail::factory()->create([
        'created_at' => '2026-10-17 23:30:00', // 2026-10-18 00:30 local
    ]);

    $series = collect(resolve(AuditReportQuery::class)->handle([
        'date_from' => '2026-10-17',
        'date_to' => '2026-10-18',
    ])['series']);

    expect($series->firstWhere('date', '2026-10-18')->value)->toBe(1);
});

it('exports audit report stream using fallback timezone when display_timezone is not set', function (): void {
    config(['app.display_timezone' => null]);

    $stream = resolve(ExportAuditReportStream::class);
    $response = $stream->handle();

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});

it('exports audit report stream using configured display timezone', function (): void {
    config(['app.display_timezone' => 'Africa/Algiers']);

    $stream = resolve(ExportAuditReportStream::class);
    $response = $stream->handle();

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});

it('does not load every row to build the time series', function (): void {
    AuditTrail::factory()->count(50)->create();
    DB::enableQueryLog();

    resolve(AuditReportQuery::class)->handle([]);

    $hasPluckQuery = collect(DB::getQueryLog())
        ->contains(fn (array $q): bool => str_contains($q['query'], 'select "created_at" from') || str_contains($q['query'], 'select `created_at` from'));

    $hasHourlyGroupQuery = collect(DB::getQueryLog())
        ->contains(fn (array $q): bool => str_contains(mb_strtolower($q['query']), 'group by') && str_contains(mb_strtolower($q['query']), 'bucket'));

    expect($hasPluckQuery)->toBeFalse()
        ->and($hasHourlyGroupQuery)->toBeTrue();
});

it('correctly attributes events around midnight for half-hour timezones like Asia/Kolkata', function (): void {
    config(['app.display_timezone' => 'Asia/Kolkata']);

    AuditTrail::query()->delete();

    // 18:29 UTC on Oct 17 -> 23:59 IST on Oct 17 (day N)
    AuditTrail::factory()->create(['created_at' => '2026-10-17 18:29:00']);
    // 18:31 UTC on Oct 17 -> 00:01 IST on Oct 18 (day N+1)
    AuditTrail::factory()->create(['created_at' => '2026-10-17 18:31:00']);
    // 18:59 UTC on Oct 17 -> 00:29 IST on Oct 18 (day N+1)
    AuditTrail::factory()->create(['created_at' => '2026-10-17 18:59:00']);

    $result = resolve(AuditReportQuery::class)->handle([
        'date_from' => '2026-10-17',
        'date_to' => '2026-10-18',
    ]);

    $series = collect($result['series']);
    expect($series->firstWhere('date', '2026-10-17')->value)->toBe(1)
        ->and($series->firstWhere('date', '2026-10-18')->value)->toBe(2);

    $seriesTotal = $series->sum('value');
    $summaryTotalEvents = collect($result['summary'])->firstWhere('id', 'total_events')?->value;
    expect($seriesTotal)->toBe($summaryTotalEvents);
});

it('correctly attributes events around midnight for 45-minute timezones like Asia/Kathmandu', function (): void {
    config(['app.display_timezone' => 'Asia/Kathmandu']);

    AuditTrail::query()->delete();

    // 18:14 UTC on Oct 17 -> 23:59 NPT on Oct 17 (day N)
    AuditTrail::factory()->create(['created_at' => '2026-10-17 18:14:00']);
    // 18:16 UTC on Oct 17 -> 00:01 NPT on Oct 18 (day N+1)
    AuditTrail::factory()->create(['created_at' => '2026-10-17 18:16:00']);

    $result = resolve(AuditReportQuery::class)->handle([
        'date_from' => '2026-10-17',
        'date_to' => '2026-10-18',
    ]);

    $series = collect($result['series']);
    expect($series->firstWhere('date', '2026-10-17')->value)->toBe(1)
        ->and($series->firstWhere('date', '2026-10-18')->value)->toBe(1);

    $seriesTotal = $series->sum('value');
    $summaryTotalEvents = collect($result['summary'])->firstWhere('id', 'total_events')?->value;
    expect($seriesTotal)->toBe($summaryTotalEvents);
});

it('correctly attributes events for whole-hour timezones like Europe/Paris', function (): void {
    config(['app.display_timezone' => 'Europe/Paris']);

    AuditTrail::query()->delete();

    AuditTrail::factory()->create(['created_at' => '2026-10-17 21:59:00']);
    AuditTrail::factory()->create(['created_at' => '2026-10-17 22:01:00']);

    $result = resolve(AuditReportQuery::class)->handle([
        'date_from' => '2026-10-17',
        'date_to' => '2026-10-18',
    ]);

    $series = collect($result['series']);
    $seriesTotal = $series->sum('value');
    $summaryTotalEvents = collect($result['summary'])->firstWhere('id', 'total_events')?->value;
    expect($seriesTotal)->toBe(2)
        ->and($seriesTotal)->toBe($summaryTotalEvents);
});
