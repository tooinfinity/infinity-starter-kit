<?php

declare(strict_types=1);

use App\Data\Reporting\ReportDateRange;
use App\Queries\Reporting\TimeBucketSql;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;

test('report date range resolves defaults when no filters provided', function (): void {
    Date::setTestNow('2026-10-24 12:00:00');

    $range = ReportDateRange::fromFilters();

    expect($range->dateFrom)->toBe('2026-09-25')
        ->and($range->dateTo)->toBe('2026-10-24')
        ->and($range->timezone)->toBe('UTC')
        ->and($range->storedTimezone)->toBe('UTC')
        ->and($range->from->toDateTimeString())->toBe('2026-09-25 00:00:00')
        ->and($range->to->toDateTimeString())->toBe('2026-10-24 23:59:59');

    Date::setTestNow();
});

test('report date range resolves explicit date filters and custom timezone', function (): void {
    $range = ReportDateRange::fromFilters([
        'date_from' => '2026-10-10',
        'date_to' => '2026-10-15',
    ], 'Asia/Kolkata');

    expect($range->dateFrom)->toBe('2026-10-10')
        ->and($range->dateTo)->toBe('2026-10-15')
        ->and($range->timezone)->toBe('Asia/Kolkata')
        ->and($range->storedTimezone)->toBe('UTC')
        // 2026-10-10 00:00:00 in Asia/Kolkata (+05:30) is 2026-10-09 18:30:00 UTC
        ->and($range->from->toDateTimeString())->toBe('2026-10-09 18:30:00')
        // 2026-10-15 23:59:59 in Asia/Kolkata (+05:30) is 2026-10-15 18:29:59 UTC
        ->and($range->to->toDateTimeString())->toBe('2026-10-15 18:29:59');
});

test('report date range creates daily carbon period', function (): void {
    $range = ReportDateRange::fromFilters([
        'date_from' => '2026-10-10',
        'date_to' => '2026-10-12',
    ]);

    $dates = collect($range->period())->map(fn (Carbon $date): string => $date->toDateString())->all();

    expect($dates)->toBe(['2026-10-10', '2026-10-11', '2026-10-12']);
});

test('report date range bucketDate converts stored bucket to display timezone date', function (): void {
    $range = ReportDateRange::fromFilters([
        'date_from' => '2026-10-17',
        'date_to' => '2026-10-18',
    ], 'Asia/Kolkata');

    // 18:15 UTC + 5:30 = 23:45 IST on Oct 17
    expect($range->bucketDate('2026-10-17 18:15:00'))->toBe('2026-10-17')
        // 18:30 UTC + 5:30 = 00:00 IST on Oct 18
        ->and($range->bucketDate('2026-10-17 18:30:00'))->toBe('2026-10-18');
});

test('report date range resolveTimezone prioritizes explicit then config then default', function (): void {
    expect(ReportDateRange::resolveTimezone('Asia/Tokyo'))->toBe('Asia/Tokyo');

    config(['app.display_timezone' => 'America/New_York']);
    expect(ReportDateRange::resolveTimezone())->toBe('America/New_York');

    config(['app.display_timezone' => null]);
    config(['app.timezone' => 'UTC']);
    expect(ReportDateRange::resolveTimezone())->toBe('UTC');
});

test('report date range timeBucketSql returns expected expressions across drivers', function (): void {
    expect(ReportDateRange::timeBucketSql('sqlite'))->toBe("strftime('%Y-%m-%d %H:', created_at) || printf('%02d:00', strftime('%M', created_at) - (strftime('%M', created_at) % 15))")
        ->and(ReportDateRange::timeBucketSql('pgsql'))->toBe("to_char(created_at, 'YYYY-MM-DD HH24:') || lpad((extract(minute from created_at)::integer - (extract(minute from created_at)::integer % 15))::text, 2, '0') || ':00'")
        ->and(ReportDateRange::timeBucketSql('sqlsrv'))->toBe("FORMAT(DATEADD(minute, -(DATEPART(minute, created_at) % 15), created_at), 'yyyy-MM-dd HH:mm:00')")
        ->and(ReportDateRange::timeBucketSql('mysql'))->toBe("CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d %H:'), LPAD(MINUTE(created_at) - (MINUTE(created_at) % 15), 2, '0'), ':00')")
        ->and(ReportDateRange::timeBucketSql('mariadb'))->toBe("CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d %H:'), LPAD(MINUTE(created_at) - (MINUTE(created_at) % 15), 2, '0'), ':00')")
        ->and(ReportDateRange::timeBucketSql('unknown'))->toBe("CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d %H:'), LPAD(MINUTE(created_at) - (MINUTE(created_at) % 15), 2, '0'), ':00')")
        ->and(ReportDateRange::timeBucketSql('sqlite', 'happened_at'))->toBe("strftime('%Y-%m-%d %H:', happened_at) || printf('%02d:00', strftime('%M', happened_at) - (strftime('%M', happened_at) % 15))");
});

test('TimeBucketSql helper generates driver-specific SQL expressions including sqlsrv', function (): void {
    expect(TimeBucketSql::forDriver('sqlsrv', 'logged_at'))
        ->toBe("FORMAT(DATEADD(minute, -(DATEPART(minute, logged_at) % 15), logged_at), 'yyyy-MM-dd HH:mm:00')")
        ->and(TimeBucketSql::forDriver('sqlsrv'))
        ->toBe("FORMAT(DATEADD(minute, -(DATEPART(minute, created_at) % 15), created_at), 'yyyy-MM-dd HH:mm:00')")
        ->and(TimeBucketSql::forDriver('sqlite'))
        ->toBe("strftime('%Y-%m-%d %H:', created_at) || printf('%02d:00', strftime('%M', created_at) - (strftime('%M', created_at) % 15))");
});
