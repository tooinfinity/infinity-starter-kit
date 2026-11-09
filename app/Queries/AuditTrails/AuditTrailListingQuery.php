<?php

declare(strict_types=1);

namespace App\Queries\AuditTrails;

use App\Models\AuditTrail;
use App\Services\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;

final readonly class AuditTrailListingQuery
{
    /**
     * @param  array{search?: string|null, event?: string|null, user_id?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, AuditTrail>
     */
    public function handle(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        /** @var Builder<AuditTrail> $query */
        $query = AuditTrail::query()->with('user');

        if (! empty($filters['search'])) {
            $search = mb_trim($filters['search']);
            $prefix = LikePattern::startsWith($search);
            $contains = LikePattern::contains($search);

            $query->where(function (Builder $q) use ($prefix, $contains): void {
                LikePattern::where($q, 'ip_address', $prefix);
                LikePattern::orWhere($q, 'auditable_id', $prefix);
                LikePattern::orWhere($q, 'actor_name', $contains);
                LikePattern::orWhere($q, 'actor_email', $contains);
                $q->orWhereHas('user', function (Builder $userQuery) use ($contains): void {
                    LikePattern::where($userQuery, 'name', $contains);
                    LikePattern::orWhere($userQuery, 'email', $contains);
                });
            });
        }

        if (! empty($filters['event']) && $filters['event'] !== 'all') {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
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
