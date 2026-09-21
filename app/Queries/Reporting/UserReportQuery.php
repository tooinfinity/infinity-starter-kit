<?php

declare(strict_types=1);

namespace App\Queries\Reporting;

use App\Data\Reporting\ReportSummaryCardData;
use App\Data\Reporting\ReportTimeSeriesPointData;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

/* @chisel-reporting */

final readonly class UserReportQuery
{
    /**
     * @var array<string, string>
     */
    private const array ALLOWED_SORTS = [
        'created_at' => 'created_at',
        'name' => 'name',
        'email' => 'email',
    ];

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, status?: string|null, search?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return array{
     *     summary: list<ReportSummaryCardData>,
     *     series: list<ReportTimeSeriesPointData>,
     *     paginated: LengthAwarePaginator<int, User>,
     *     date_from: string,
     *     date_to: string
     * }
     */
    public function handle(array $filters = [], int $perPage = 15): array
    {
        $dateFromStr = ! empty($filters['date_from'])
            ? $filters['date_from']
            : Date::now()->subDays(29)->toDateString();

        $dateToStr = ! empty($filters['date_to'])
            ? $filters['date_to']
            : Date::now()->toDateString();

        $from = Date::parse($dateFromStr)->startOfDay();
        $to = Date::parse($dateToStr)->endOfDay();

        // 1. Summary Cards
        $totalUsers = User::query()->count();
        $activeUsers = User::query()->where('is_active', true)->count();
        $inactiveUsers = User::query()->where('is_active', false)->count();
        $newUsers = User::query()->whereBetween('created_at', [$from, $to])->count();

        $summary = [
            new ReportSummaryCardData(
                id: 'total_users',
                title: __('reports.metrics.total_users'),
                value: $totalUsers,
                description: __('reports.metrics.total_users_description'),
                icon: 'Users',
            ),
            new ReportSummaryCardData(
                id: 'active_users',
                title: __('reports.metrics.active_users'),
                value: $activeUsers,
                description: __('reports.metrics.active_users_description'),
                icon: 'UserCheck',
            ),
            new ReportSummaryCardData(
                id: 'inactive_users',
                title: __('reports.metrics.inactive_users'),
                value: $inactiveUsers,
                description: __('reports.metrics.inactive_users_description'),
                icon: 'UserX',
            ),
            new ReportSummaryCardData(
                id: 'new_users',
                title: __('reports.metrics.new_users_in_period'),
                value: $newUsers,
                description: __('reports.metrics.new_users_in_period_description'),
                icon: 'UserPlus',
            ),
        ];

        // 2. Time-series (registrations per day)
        /** @var list<object{date: string, count: int|string}> $rawSeries */
        $rawSeries = DB::table('users')
            ->selectRaw('DATE(created_at) as date, count(*) as count')
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->all();

        /** @var array<string, int> $countsByDate */
        $countsByDate = [];
        foreach ($rawSeries as $item) {
            $countsByDate[$item->date] = (int) $item->count;
        }

        $series = [];
        $period = CarbonPeriod::create($from->copy()->startOfDay(), '1 day', $to->copy()->startOfDay());
        foreach ($period as $date) {
            /** @var Carbon $date */
            $key = $date->toDateString();
            $series[] = new ReportTimeSeriesPointData(
                date: $key,
                value: $countsByDate[$key] ?? 0,
            );
        }

        // 3. Paginated rows
        $query = $this->buildFilteredQuery($filters, $from, $to);

        $sortField = self::ALLOWED_SORTS[$filters['sort'] ?? 'created_at'] ?? 'created_at';
        $direction = mb_strtolower($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $paginated = $query->orderBy($sortField, $direction)->paginate($perPage)->withQueryString();

        return [
            'summary' => $summary,
            'series' => $series,
            'paginated' => $paginated,
            'date_from' => $dateFromStr,
            'date_to' => $dateToStr,
        ];
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, status?: string|null, search?: string|null, sort?: string|null, direction?: string|null}  $filters
     * @return Builder<User>
     */
    public function buildFilteredQuery(array $filters, CarbonInterface $from, CarbonInterface $to): Builder
    {
        /** @var Builder<User> $query */
        $query = User::query();

        if (trait_exists(HasRoles::class)) {
            $query->with('roles');
        }

        $query->whereBetween('created_at', [$from, $to]);

        if (isset($filters['status']) && $filters['status'] !== 'all') {
            if ($filters['status'] === 'active') {
                $query->where('is_active', true);
            } elseif ($filters['status'] === 'inactive') {
                $query->where('is_active', false);
            }
        }

        if (! empty($filters['search'])) {
            $search = mb_trim($filters['search']);
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', sprintf('%%%s%%', $search))
                    ->orWhere('email', 'like', sprintf('%%%s%%', $search));
            });
        }

        return $query;
    }
}

/* @end-chisel-reporting */
