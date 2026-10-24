<?php

declare(strict_types=1);

namespace App\Data\Reporting;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ReportDateRange
{
    public function __construct(
        public CarbonInterface $from,
        public CarbonInterface $to,
        public string $dateFrom,
        public string $dateTo,
        public string $timezone,
        public string $storedTimezone,
    ) {}

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, ...}  $filters
     */
    public static function fromFilters(array $filters = [], ?string $timezone = null, ?string $storedTimezone = null): self
    {
        $resolvedTimezone = self::resolveTimezone($timezone);
        $resolvedStoredTimezone = $storedTimezone ?? config()->string('app.timezone', 'UTC');

        $dateFromStr = ! empty($filters['date_from'])
            ? $filters['date_from']
            : Date::now($resolvedTimezone)->subDays(29)->toDateString();

        $dateToStr = ! empty($filters['date_to'])
            ? $filters['date_to']
            : Date::now($resolvedTimezone)->toDateString();

        $from = Date::parse($dateFromStr, $resolvedTimezone)->startOfDay()->setTimezone($resolvedStoredTimezone);
        $to = Date::parse($dateToStr, $resolvedTimezone)->endOfDay()->setTimezone($resolvedStoredTimezone);

        return new self(
            from: $from,
            to: $to,
            dateFrom: $dateFromStr,
            dateTo: $dateToStr,
            timezone: $resolvedTimezone,
            storedTimezone: $resolvedStoredTimezone,
        );
    }

    public static function resolveTimezone(?string $timezone = null): string
    {
        if (is_string($timezone) && $timezone !== '') {
            return $timezone;
        }

        $configured = config('app.display_timezone');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return config()->string('app.timezone', 'UTC');
    }

    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function timeBucketSql(?string $driver = null, string $column = 'created_at'): string
    {
        $driver ??= DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d %H:', {$column}) || printf('%02d:00', strftime('%M', {$column}) - (strftime('%M', {$column}) % 15))",
            'pgsql' => "to_char({$column}, 'YYYY-MM-DD HH24:') || lpad((extract(minute from {$column})::integer - (extract(minute from {$column})::integer % 15))::text, 2, '0') || ':00'",
            'sqlsrv' => "FORMAT(DATEADD(minute, -(DATEPART(minute, {$column}) % 15), {$column}), 'yyyy-MM-dd HH:mm:00')",
            default => "CONCAT(DATE_FORMAT({$column}, '%Y-%m-%d %H:'), LPAD(MINUTE({$column}) - (MINUTE({$column}) % 15), 2, '0'), ':00')",
        };
    }

    /**
     * @return CarbonPeriod<Carbon>
     */
    public function period(): CarbonPeriod
    {
        /** @var CarbonPeriod<Carbon> $period */
        $period = CarbonPeriod::create(
            Date::parse($this->dateFrom, $this->timezone)->startOfDay(),
            '1 day',
            Date::parse($this->dateTo, $this->timezone)->startOfDay(),
        );

        return $period;
    }

    public function bucketDate(string|int $bucket): string
    {
        return Date::parse((string) $bucket, $this->storedTimezone)
            ->setTimezone($this->timezone)
            ->toDateString();
    }
}
