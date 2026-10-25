<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

test('scheduler deletes read notifications older than default configured days and keeps newer and unread ones', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-01 00:00:00'));

    // 1. Old read notification (read 31 days ago)
    $user->notify(new PasswordChanged);
    $oldReadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $oldReadId)->update([
        'read_at' => Date::now()->subDays(31),
        'created_at' => Date::now()->subDays(35),
    ]);

    // 2. Recent read notification (read 10 days ago)
    $user->notify(new PasswordChanged);
    $recentReadId = $user->notifications()->whereNotIn('id', [$oldReadId])->first()->id;
    DB::table('notifications')->where('id', $recentReadId)->update([
        'read_at' => Date::now()->subDays(10),
        'created_at' => Date::now()->subDays(15),
    ]);

    // 3. Old unread notification (created 45 days ago)
    $user->notify(new PasswordChanged);
    $oldUnreadId = $user->notifications()->whereNotIn('id', [$oldReadId, $recentReadId])->first()->id;
    DB::table('notifications')->where('id', $oldUnreadId)->update([
        'read_at' => null,
        'created_at' => Date::now()->subDays(45),
    ]);

    // 4. New unread notification
    $user->notify(new PasswordChanged);
    $recentUnreadId = $user->notifications()->whereNotIn('id', [$oldReadId, $recentReadId, $oldUnreadId])->first()->id;

    $this->artisan('schedule:run');

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentReadId)->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('id', $recentUnreadId)->exists())->toBeTrue();
});

test('scheduler deletes read notifications based on custom configured retention days', function (): void {
    config(['notifications.retention_days' => 7]);

    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-02 00:00:00'));

    // Read 8 days ago (older than 7 days)
    $user->notify(new PasswordChanged);
    $oldReadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $oldReadId)->update([
        'read_at' => Date::now()->subDays(8),
    ]);

    // Read 5 days ago (newer than 7 days)
    $user->notify(new PasswordChanged);
    $recentReadId = $user->notifications()->where('id', '!=', $oldReadId)->first()->id;
    DB::table('notifications')->where('id', $recentReadId)->update([
        'read_at' => Date::now()->subDays(5),
    ]);

    $this->artisan('schedule:run');

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentReadId)->exists())->toBeTrue();
});

test('scheduler does not delete read notifications when retention is disabled', function (): void {
    config(['notifications.retention_days' => 0]);

    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-03 00:00:00'));

    $user->notify(new PasswordChanged);
    $oldReadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $oldReadId)->update([
        'read_at' => Date::now()->subDays(100),
    ]);

    $this->artisan('schedule:run');

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeTrue();
});

test('scheduler deletes unread notifications when unread retention is configured', function (): void {
    config(['notifications.unread_retention_days' => 60]);

    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-04 00:00:00'));

    // Unread older than 60 days
    $user->notify(new PasswordChanged);
    $oldUnreadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $oldUnreadId)->update([
        'read_at' => null,
        'created_at' => Date::now()->subDays(65),
    ]);

    // Unread newer than 60 days
    $user->notify(new PasswordChanged);
    $recentUnreadId = $user->notifications()->where('id', '!=', $oldUnreadId)->first()->id;
    DB::table('notifications')->where('id', $recentUnreadId)->update([
        'read_at' => null,
        'created_at' => Date::now()->subDays(20),
    ]);

    $this->artisan('schedule:run');

    expect(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentUnreadId)->exists())->toBeTrue();
});
