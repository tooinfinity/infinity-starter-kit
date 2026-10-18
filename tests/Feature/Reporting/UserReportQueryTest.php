<?php

declare(strict_types=1);

use App\Models\User;
use App\Queries\Reporting\ExportUserReportStream;
use App\Queries\Reporting\UserReportQuery;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\StreamedResponse;

test('user report query handles empty dataset gracefully', function (): void {
    User::query()->delete();

    $query = new UserReportQuery;
    $result = $query->handle([
        'date_from' => Date::now()->subDays(5)->toDateString(),
        'date_to' => Date::now()->toDateString(),
    ]);

    expect($result['summary'])->toHaveCount(4)
        ->and($result['summary'][0]->value)->toBe(0)
        ->and($result['summary'][1]->value)->toBe(0)
        ->and($result['summary'][2]->value)->toBe(0)
        ->and($result['summary'][3]->value)->toBe(0)
        ->and($result['series'])->toHaveCount(6)
        ->and($result['paginated']->total())->toBe(0);
});

test('user report query computes accurate summary metrics and time series', function (): void {
    User::query()->delete();

    $baseDate = Date::now()->subDays(3);

    User::factory()->create([
        'name' => 'Active User 1',
        'email' => 'active1@example.com',
        'is_active' => true,
        'created_at' => $baseDate->copy()->startOfDay()->addHours(1),
    ]);

    User::factory()->create([
        'name' => 'Active User 2',
        'email' => 'active2@example.com',
        'is_active' => true,
        'created_at' => $baseDate->copy()->addDays(1)->startOfDay()->addHours(2),
    ]);

    User::factory()->create([
        'name' => 'Inactive User',
        'email' => 'inactive@example.com',
        'is_active' => false,
        'created_at' => $baseDate->copy()->addDays(2)->startOfDay()->addHours(3),
    ]);

    // Outside date range user
    User::factory()->create([
        'name' => 'Old User',
        'email' => 'old@example.com',
        'is_active' => true,
        'created_at' => $baseDate->copy()->subDays(10),
    ]);

    $query = new UserReportQuery;
    $result = $query->handle([
        'date_from' => $baseDate->toDateString(),
        'date_to' => $baseDate->copy()->addDays(2)->toDateString(),
    ]);

    // Total: 4, Active: 3, Inactive: 1, New in period: 3
    expect($result['summary'][0]->value)->toBe(4)
        ->and($result['summary'][1]->value)->toBe(3)
        ->and($result['summary'][2]->value)->toBe(1)
        ->and($result['summary'][3]->value)->toBe(3)
        ->and($result['paginated']->total())->toBe(3);

    // Verify time series has points for all 3 days in period
    expect($result['series'])->toHaveCount(3);
    $seriesMap = collect($result['series'])->pluck('value', 'date')->all();
    expect($seriesMap[$baseDate->toDateString()])->toBe(1)
        ->and($seriesMap[$baseDate->copy()->addDays(1)->toDateString()])->toBe(1)
        ->and($seriesMap[$baseDate->copy()->addDays(2)->toDateString()])->toBe(1);
});

test('user report query filters by status correctly', function (): void {
    User::query()->delete();

    $today = Date::now();

    User::factory()->create([
        'name' => 'Active John',
        'email' => 'john@example.com',
        'is_active' => true,
        'created_at' => $today,
    ]);

    User::factory()->create([
        'name' => 'Inactive Jane',
        'email' => 'jane@example.com',
        'is_active' => false,
        'created_at' => $today,
    ]);

    $query = new UserReportQuery;

    $activeResult = $query->handle([
        'date_from' => $today->copy()->subDay()->toDateString(),
        'date_to' => $today->copy()->addDay()->toDateString(),
        'status' => 'active',
    ]);

    expect($activeResult['paginated']->total())->toBe(1)
        ->and($activeResult['paginated']->first()?->name)->toBe('Active John');

    $inactiveResult = $query->handle([
        'date_from' => $today->copy()->subDay()->toDateString(),
        'date_to' => $today->copy()->addDay()->toDateString(),
        'status' => 'inactive',
    ]);

    expect($inactiveResult['paginated']->total())->toBe(1)
        ->and($inactiveResult['paginated']->first()?->name)->toBe('Inactive Jane');
});

test('user report query searches by name and email and supports sorting', function (): void {
    User::query()->delete();

    $today = Date::now();

    User::factory()->create([
        'name' => 'Alice Developer',
        'email' => 'alice@test.org',
        'created_at' => $today->copy()->subHours(2),
    ]);

    User::factory()->create([
        'name' => 'Bob Engineer',
        'email' => 'bob@company.com',
        'created_at' => $today->copy()->subHours(1),
    ]);

    $query = new UserReportQuery;

    $searchNameResult = $query->handle([
        'date_from' => $today->copy()->subDay()->toDateString(),
        'date_to' => $today->copy()->addDay()->toDateString(),
        'search' => 'Alice',
    ]);
    expect($searchNameResult['paginated']->total())->toBe(1)
        ->and($searchNameResult['paginated']->first()?->name)->toBe('Alice Developer');

    $searchEmailResult = $query->handle([
        'date_from' => $today->copy()->subDay()->toDateString(),
        'date_to' => $today->copy()->addDay()->toDateString(),
        'search' => 'company.com',
    ]);
    expect($searchEmailResult['paginated']->total())->toBe(1)
        ->and($searchEmailResult['paginated']->first()?->name)->toBe('Bob Engineer');

    $sortedResult = $query->handle([
        'date_from' => $today->copy()->subDay()->toDateString(),
        'date_to' => $today->copy()->addDay()->toDateString(),
        'sort' => 'name',
        'direction' => 'asc',
    ]);
    expect($sortedResult['paginated']->first()?->name)->toBe('Alice Developer');
});

it('buckets report days in the configured display timezone', function (): void {
    config(['app.display_timezone' => 'Africa/Algiers']); // UTC+1

    User::factory()->create(['created_at' => '2026-10-17 23:30:00']); // 2026-10-18 00:30 local

    $series = collect(resolve(UserReportQuery::class)->handle([
        'date_from' => '2026-10-17',
        'date_to' => '2026-10-18',
    ])['series']);

    expect($series->firstWhere('date', '2026-10-18')->value)->toBe(1);
});

it('exports report stream using fallback timezone when display_timezone is not set', function (): void {
    config(['app.display_timezone' => null]);

    $stream = resolve(ExportUserReportStream::class);
    $response = $stream->handle();

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});

it('exports report stream using configured display timezone', function (): void {
    config(['app.display_timezone' => 'Africa/Algiers']);

    $stream = resolve(ExportUserReportStream::class);
    $response = $stream->handle();

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});
