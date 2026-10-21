<?php

declare(strict_types=1);

/* @chisel-notifications */
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    DB::table('notifications')
        ->whereNotNull('read_at')
        ->where('read_at', '<', Date::now()->subDays(30))
        ->delete();
})->daily();
/* @end-chisel-notifications */

/* @chisel-audit-trails */
Schedule::command('audit:prune')->daily();
/* @end-chisel-audit-trails */
