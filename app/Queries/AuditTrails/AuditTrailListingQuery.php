<?php

declare(strict_types=1);

namespace App\Queries\AuditTrails;

use App\Models\Activity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final readonly class AuditTrailListingQuery
{
    /**
     * @return literal-string
     */
    public static function jsonLikeEscapeSql(string $path, ?string $driver = null): string
    {
        $driver ??= DB::connection()->getDriverName();

        return match ($path) {
            'actor.name' => match ($driver) {
                'sqlite' => "json_extract(properties, '$.actor.name') LIKE ? ESCAPE ?",
                'pgsql' => "properties->'actor'->>'name' LIKE ? ESCAPE ?",
                'sqlsrv' => "JSON_VALUE(properties, '$.actor.name') LIKE ? ESCAPE ?",
                default => "json_unquote(json_extract(properties, '$.actor.name')) LIKE ? ESCAPE ?",
            },
            'actor.email' => match ($driver) {
                'sqlite' => "json_extract(properties, '$.actor.email') LIKE ? ESCAPE ?",
                'pgsql' => "properties->'actor'->>'email' LIKE ? ESCAPE ?",
                'sqlsrv' => "JSON_VALUE(properties, '$.actor.email') LIKE ? ESCAPE ?",
                default => "json_unquote(json_extract(properties, '$.actor.email')) LIKE ? ESCAPE ?",
            },
            'request.ip_address' => match ($driver) {
                'sqlite' => "json_extract(properties, '$.request.ip_address') LIKE ? ESCAPE ?",
                'pgsql' => "properties->'request'->>'ip_address' LIKE ? ESCAPE ?",
                'sqlsrv' => "JSON_VALUE(properties, '$.request.ip_address') LIKE ? ESCAPE ?",
                default => "json_unquote(json_extract(properties, '$.request.ip_address')) LIKE ? ESCAPE ?",
            },
            default => throw new InvalidArgumentException("Unsupported JSON path: {$path}"),
        };
    }

    /**
     * @param  array{search?: string|null, event?: string|null, user_id?: string|null, date_from?: string|null, date_to?: string|null}  $filters
     * @return LengthAwarePaginator<int, Activity>
     */
    public function handle(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        /** @var Builder<Activity> $query */
        $query = Activity::query()->with('user');

        if (! empty($filters['search'])) {
            $search = mb_trim($filters['search']);
            $escaped = addcslashes($search, '%_\\');
            $prefix = "{$escaped}%";
            $contains = "%{$escaped}%";

            $query->where(function (Builder $q) use ($prefix, $contains): void {
                $q->whereRaw(self::jsonLikeEscapeSql('request.ip_address'), [$prefix, '\\'])
                    ->orWhereRaw('subject_id LIKE ? ESCAPE ?', [$prefix, '\\'])
                    ->orWhereRaw(self::jsonLikeEscapeSql('actor.name'), [$contains, '\\'])
                    ->orWhereRaw(self::jsonLikeEscapeSql('actor.email'), [$contains, '\\'])
                    ->orWhereHas('user', function (Builder $userQuery) use ($contains): void {
                        $userQuery->whereRaw('name LIKE ? ESCAPE ?', [$contains, '\\'])
                            ->orWhereRaw('email LIKE ? ESCAPE ?', [$contains, '\\']);
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
