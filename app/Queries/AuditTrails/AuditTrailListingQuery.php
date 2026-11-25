<?php

declare(strict_types=1);

namespace App\Queries\AuditTrails;

use App\Models\Activity;
use App\Models\User;
use App\Services\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;

final readonly class AuditTrailListingQuery
{
    /**
     * @param  array{search?: string|null, event?: string|null, user_id?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function handle(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        /** @var Builder<Activity> $query */
        $query = Activity::query()->with('causer');

        if (! empty($filters['search'])) {
            $search = mb_trim($filters['search']);
            $prefix = LikePattern::startsWith($search);
            $contains = LikePattern::contains($search);

            $query->where(function (Builder $q) use ($prefix, $contains): void {
                LikePattern::where($q, 'ip_address', $prefix);
                LikePattern::orWhere($q, 'subject_id', $prefix);
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

        if (! empty($filters['event']) && $filters['event'] !== 'all') {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('causer_id', $filters['user_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Date::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Date::parse($filters['date_to'])->endOfDay());
        }

        return $query->latest('created_at')->paginate($perPage)->withQueryString();
    }
}
