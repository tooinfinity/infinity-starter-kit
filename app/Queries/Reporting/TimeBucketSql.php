<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use Illuminate\Support\Facades\DB;

final readonly class TimeBucketSql
{
    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function forDriver(?string $driver = null, string $column = 'created_at'): string
    {
        $driver ??= DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d %H:', {$column}) || printf('%02d:00', strftime('%M', {$column}) - (strftime('%M', {$column}) % 15))",
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD HH24:') || lpad((extract(minute from {$column})::integer - (extract(minute from {$column})::integer % 15))::text, 2, '0') || ':00'",
            'sqlsrv' => "FORMAT(DATEADD(minute, -(DATEPART(minute, {$column}) % 15), {$column}), 'yyyy-MM-dd HH:mm:00')",
            default => "CONCAT(DATE_FORMAT({$column}, '%Y-%m-%d %H:'), LPAD(MINUTE({$column}) - (MINUTE({$column}) % 15), 2, '0'), ':00')",
        };
    }
}
