<?php

declare(strict_types=1);

namespace App\Queries\AuditTrails;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Build an Eloquent query for Activity that combines Spatie's activity_log with
 * any historical records in legacy audit_trails that have not yet been migrated.
 *
 * TEMPORARY COMPATIBILITY MECHANISM:
 * Maintains read-visibility for historical audit_trails records in PR #3 without
 * destructive database mutations. Can be removed in PR #4 once legacy audit_trails
 * is retired and data backfill is complete.
 */
final readonly class HistoricalAuditTrailQuery
{
    /**
     * @return Builder<Activity>
     */
    public static function build(): Builder
    {
        if (! Schema::hasTable('audit_trails')) {
            $spatieQuery = DB::table('activity_log')->selectRaw('
                id,
                log_name,
                description,
                subject_type,
                subject_id,
                event,
                causer_type,
                causer_id,
                attribute_changes,
                properties,
                ip_address,
                user_agent,
                created_at,
                NULL as legacy_old_values,
                NULL as legacy_new_values,
                NULL as legacy_actor_name,
                NULL as legacy_actor_email,
                NULL as legacy_url,
                NULL as legacy_tags
            ');

            /** @var Builder<Activity> $query */
            $query = Activity::query()->fromSub($spatieQuery, 'activity_log');

            return $query;
        }

        $spatieQuery = DB::table('activity_log')->selectRaw('
            id,
            log_name,
            description,
            subject_type,
            subject_id,
            event,
            causer_type,
            causer_id,
            attribute_changes,
            properties,
            ip_address,
            user_agent,
            created_at,
            NULL as legacy_old_values,
            NULL as legacy_new_values,
            NULL as legacy_actor_name,
            NULL as legacy_actor_email,
            NULL as legacy_url,
            NULL as legacy_tags
        ');

        $userMorph = (new User)->getMorphClass();

        $legacyQuery = DB::table('audit_trails')->selectRaw('
            id,
            \'default\' as log_name,
            event as description,
            auditable_type as subject_type,
            auditable_id as subject_id,
            event,
            CASE WHEN user_id IS NOT NULL THEN ? ELSE NULL END as causer_type,
            CAST(user_id AS CHAR(36)) as causer_id,
            NULL as attribute_changes,
            NULL as properties,
            ip_address,
            user_agent,
            created_at,
            old_values as legacy_old_values,
            new_values as legacy_new_values,
            actor_name as legacy_actor_name,
            actor_email as legacy_actor_email,
            url as legacy_url,
            tags as legacy_tags
        ', [$userMorph])->whereNotIn('id', DB::table('activity_log')->select('id'));

        $union = $spatieQuery->unionAll($legacyQuery);

        /** @var Builder<Activity> $query */
        $query = Activity::query()->fromSub($union, 'activity_log');

        return $query;
    }
}
