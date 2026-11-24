<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\Data\Reporting\ReportBreakdownItemData;
use App\Data\Reporting\ReportDateRange;
use App\Data\Reporting\ReportSummaryCardData;
use App\Data\Reporting\ReportTimeSeriesPointData;
use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\User;
use App\Queries\AuditTrails\HistoricalAuditTrailQuery;
use App\Services\LikePattern;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final readonly class AuditReportQuery
{
    /**
     * @var array<string, string>
     */
    private const array ALLOWED_SORTS = [
        'created_at' => 'created_at',
        'event' => 'event',
    ];

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, event?: string|null, user_id?: string|null, search?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return array{
     *     summary: list<ReportSummaryCardData>,
     *     series: list<ReportTimeSeriesPointData>,
     *     breakdown: list<ReportBreakdownItemData>,
     *     paginated: LengthAwarePaginator<int, Activity>,
     *     date_from: string,
     *     date_to: string
     * }
     */
    public function handle(array $filters = [], int $perPage = 15): array
    {
        $range = ReportDateRange::fromFilters($filters);
        $from = $range->from;
        $to = $range->to;

        // 1. Summary Cards
        $totalEvents = HistoricalAuditTrailQuery::build()->whereBetween('created_at', [$from, $to])->count();
        $uniqueActors = HistoricalAuditTrailQuery::build()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('causer_id')
            ->distinct('causer_id')
            ->count('causer_id');

        /** @var object{event: string, count: int|string}|null $topEventRecord */
        $topEventRecord = HistoricalAuditTrailQuery::build()
            ->selectRaw('event, count(*) as count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('event')
            ->orderByDesc('count')
            ->first();

        $topEventLabel = $topEventRecord !== null
            ? (AuditEvent::tryFrom((string) $topEventRecord->event)?->label() ?? (string) $topEventRecord->event)
            : __('reports.common.none');

        /** @var object{subject_type: string, count: int|string}|null $topResourceRecord */
        $topResourceRecord = HistoricalAuditTrailQuery::build()
            ->selectRaw('subject_type, count(*) as count')
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('subject_type')
            ->groupBy('subject_type')
            ->orderByDesc('count')
            ->first();

        $topResourceLabel = $topResourceRecord !== null
            ? class_basename((string) $topResourceRecord->subject_type)
            : __('reports.common.none');

        $summary = [
            new ReportSummaryCardData(
                id: 'total_events',
                title: __('reports.metrics.total_audit_events'),
                value: $totalEvents,
                description: __('reports.metrics.total_audit_events_description'),
                icon: 'Activity',
            ),
            new ReportSummaryCardData(
                id: 'unique_actors',
                title: __('reports.metrics.unique_actors'),
                value: $uniqueActors,
                description: __('reports.metrics.unique_actors_description'),
                icon: 'Shield',
            ),
            new ReportSummaryCardData(
                id: 'top_event',
                title: __('reports.metrics.top_event'),
                value: $topEventLabel,
                description: $topEventRecord !== null ? sprintf('%d %s', (int) $topEventRecord->count, __('reports.common.occurrences')) : __('reports.common.no_events_recorded'),
                icon: 'Zap',
            ),
            new ReportSummaryCardData(
                id: 'top_resource',
                title: __('reports.metrics.top_resource'),
                value: $topResourceLabel,
                description: $topResourceRecord !== null ? sprintf('%d %s', (int) $topResourceRecord->count, __('reports.common.occurrences')) : __('reports.common.no_resources_recorded'),
                icon: 'Layers',
            ),
        ];

        // 2. Time-series (events per day in display timezone)
        /** @var array<string, int> $countsByDate */
        $countsByDate = [];
        $bucketSql = TimeBucketSql::forDriver(column: 'created_at');

        /** @var list<object{bucket: string, count: int|string}> $rawSeries */
        $rawSeries = HistoricalAuditTrailQuery::build()
            ->selectRaw("{$bucketSql} as bucket, count(*) as count")
            ->whereBetween('created_at', [$from, $to])
            ->groupBy(DB::raw($bucketSql))
            ->orderBy('bucket')
            ->get()
            ->all();

        foreach ($rawSeries as $row) {
            $dateKey = $range->bucketDate($row->bucket);

            $countsByDate[$dateKey] = ($countsByDate[$dateKey] ?? 0) + (int) $row->count;
        }

        $series = [];
        foreach ($range->period() as $date) {
            /** @var Carbon $date */
            $key = $date->toDateString();
            $series[] = new ReportTimeSeriesPointData(
                date: $key,
                value: $countsByDate[$key] ?? 0,
            );
        }

        // 3. Breakdown by event type
        /** @var list<object{event: string|null, count: int|string}> $rawBreakdown */
        $rawBreakdown = HistoricalAuditTrailQuery::build()
            ->selectRaw('event, count(*) as count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('event')
            ->orderByDesc('count')
            ->get()
            ->all();

        $breakdown = [];
        foreach ($rawBreakdown as $row) {
            $count = (int) $row->count;
            $percentage = $totalEvents > 0 ? round(($count / $totalEvents) * 100, 1) : 0.0;
            $eventEnum = $row->event !== null ? AuditEvent::tryFrom($row->event) : null;
            $key = $row->event ?? 'unknown';
            $label = $eventEnum?->label() ?? ($row->event !== null && $row->event !== '' ? $row->event : __('reports.common.none'));
            $breakdown[] = new ReportBreakdownItemData(
                key: $key,
                label: $label,
                count: $count,
                percentage: $percentage,
            );
        }

        // 4. Paginated rows
        $query = $this->buildFilteredQuery($filters, $from, $to);

        $sortField = self::ALLOWED_SORTS[$filters['sort'] ?? 'created_at'] ?? 'created_at';
        $direction = mb_strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $paginated = $query->orderBy($sortField, $direction)->paginate($perPage)->withQueryString();

        return [
            'summary' => $summary,
            'series' => $series,
            'breakdown' => $breakdown,
            'paginated' => $paginated,
            'date_from' => $range->dateFrom,
            'date_to' => $range->dateTo,
        ];
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, event?: string|null, user_id?: string|null, search?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return Builder<Activity>
     */
    public function buildFilteredQuery(array $filters, CarbonInterface $from, CarbonInterface $to): Builder
    {
        /** @var Builder<Activity> $query */
        $query = HistoricalAuditTrailQuery::build()->with('causer');

        $query->whereBetween('created_at', [$from, $to]);

        if (! empty($filters['event']) && $filters['event'] !== 'all') {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('causer_id', $filters['user_id']);
        }

        if (! empty($filters['search'])) {
            $search = mb_trim($filters['search']);
            $prefix = LikePattern::startsWith($search);
            $contains = LikePattern::contains($search);

            $query->where(function (Builder $q) use ($prefix, $contains): void {
                LikePattern::where($q, 'ip_address', $prefix);
                LikePattern::orWhere($q, 'subject_id', $prefix);
                LikePattern::orWhere($q, 'subject_type', $prefix);
                LikePattern::orWhere($q, 'legacy_actor_name', $contains);
                LikePattern::orWhere($q, 'legacy_actor_email', $contains);
                LikePattern::orWhereJson($q, 'properties', 'actor.name', $contains);
                LikePattern::orWhereJson($q, 'properties', 'actor.email', $contains);

                $userMorphs = array_values(array_unique([
                    User::class,
                    (new User)->getMorphClass(),
                ]));
                $q->orWhere(function (Builder $causerQuery) use ($userMorphs, $contains): void {
                    $causerQuery->whereIn('causer_type', $userMorphs)
                        ->whereExists(
                            User::query()
                                ->selectRaw('1')
                                ->whereRaw('CAST(users.id AS CHAR(36)) = activity_log.causer_id')
                                ->where(function (Builder $userQuery) use ($contains): void {
                                    LikePattern::where($userQuery, 'name', $contains);
                                    LikePattern::orWhere($userQuery, 'email', $contains);
                                })
                        );
                });
            });
        }

        return $query;
    }
}
