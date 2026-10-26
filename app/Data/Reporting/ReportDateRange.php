<?php

declare(strict_types=1);

namespace App\Data\Reporting;

use App\Queries\Reporting\TimeBucketSql;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Date;

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
        return TimeBucketSql::forDriver($driver, $column);
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
