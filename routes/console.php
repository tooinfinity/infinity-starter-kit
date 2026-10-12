<?php

declare(strict_types=1);

/* @chisel-notifications */
use App\Models\User;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function (): void {
    User::query()->each(function (User $user): void {
        $user->readNotifications()->delete();
    });
})->daily();
/* @end-chisel-notifications */
