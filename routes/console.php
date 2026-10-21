<?php

declare(strict_types=1);

use App\Models\User;
/* @chisel-notifications */
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    User::query()->each(function (User $user): void {
        $user->readNotifications()->delete();
    });
})->daily();
/* @end-chisel-notifications */

/* @chisel-audit-trails */
Schedule::command('audit:prune')->daily();
/* @end-chisel-audit-trails */
