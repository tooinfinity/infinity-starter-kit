<?php

declare(strict_types=1);

use App\Queries\Reporting\TimeBucketSql;
use Illuminate\Support\Facades\DB;

test('time bucket sql formats correctly for sqlite driver', function (): void {
    $default = TimeBucketSql::forDriver('sqlite');
    $custom = TimeBucketSql::forDriver('sqlite', 'happened_at');

    expect($default)->toBe("strftime('%Y-%m-%d %H:', created_at) || printf('%02d:00', strftime('%M', created_at) - (strftime('%M', created_at) % 15))")
        ->and($custom)->toBe("strftime('%Y-%m-%d %H:', happened_at) || printf('%02d:00', strftime('%M', happened_at) - (strftime('%M', happened_at) % 15))");
});

test('time bucket sql formats correctly for pgsql driver', function (): void {
    $default = TimeBucketSql::forDriver('pgsql');
    $custom = TimeBucketSql::forDriver('pgsql', 'happened_at');

    expect($default)->toBe("to_char(created_at, 'YYYY-MM-DD HH24:') || lpad((extract(minute from created_at)::integer - (extract(minute from created_at)::integer % 15))::text, 2, '0') || ':00'")
        ->and($custom)->toBe("to_char(happened_at, 'YYYY-MM-DD HH24:') || lpad((extract(minute from happened_at)::integer - (extract(minute from happened_at)::integer % 15))::text, 2, '0') || ':00'");
});

test('time bucket sql formats correctly for sqlsrv driver', function (): void {
    $default = TimeBucketSql::forDriver('sqlsrv');
    $custom = TimeBucketSql::forDriver('sqlsrv', 'happened_at');

    expect($default)->toBe("FORMAT(DATEADD(minute, -(DATEPART(minute, created_at) % 15), created_at), 'yyyy-MM-dd HH:mm:00')")
        ->and($custom)->toBe("FORMAT(DATEADD(minute, -(DATEPART(minute, happened_at) % 15), happened_at), 'yyyy-MM-dd HH:mm:00')");
});

test('time bucket sql formats correctly for mysql and default drivers', function (): void {
    $mysql = TimeBucketSql::forDriver('mysql');
    $mariadb = TimeBucketSql::forDriver('mariadb');
    $custom = TimeBucketSql::forDriver('mysql', 'happened_at');

    expect($mysql)->toBe("CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d %H:'), LPAD(MINUTE(created_at) - (MINUTE(created_at) % 15), 2, '0'), ':00')")
        ->and($mariadb)->toBe("CONCAT(DATE_FORMAT(created_at, '%Y-%m-%d %H:'), LPAD(MINUTE(created_at) - (MINUTE(created_at) % 15), 2, '0'), ':00')")
        ->and($custom)->toBe("CONCAT(DATE_FORMAT(happened_at, '%Y-%m-%d %H:'), LPAD(MINUTE(happened_at) - (MINUTE(happened_at) % 15), 2, '0'), ':00')");
});

test('time bucket sql resolves connection driver when null is provided', function (): void {
    $activeDriver = DB::connection()->getDriverName();
    $expected = TimeBucketSql::forDriver($activeDriver);

    expect(TimeBucketSql::forDriver())->toBe($expected);
});
