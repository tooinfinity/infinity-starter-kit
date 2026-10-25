<?php

declare(strict_types=1);

/* @chisel-notifications */
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    $retentionDays = config()->integer('notifications.retention_days', 30);

    if ($retentionDays > 0) {
        DB::table('notifications')
            ->whereNotNull('read_at')
            ->where('read_at', '<', Date::now()->subDays($retentionDays))
            ->delete();
    }

    $unreadRetentionDays = config('notifications.unread_retention_days');

    if (is_numeric($unreadRetentionDays) && (int) $unreadRetentionDays > 0) {
        DB::table('notifications')
            ->whereNull('read_at')
            ->where('created_at', '<', Date::now()->subDays((int) $unreadRetentionDays))
            ->delete();
    }
})->daily();
/* @end-chisel-notifications */

/* @chisel-audit-trails */
Schedule::command('audit:prune')->daily();
/* @end-chisel-audit-trails */
