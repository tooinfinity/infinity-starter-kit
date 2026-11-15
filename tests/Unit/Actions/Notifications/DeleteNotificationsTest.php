<?php

declare(strict_types=1);

use App\Actions\Notifications\DeleteNotifications;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

it('deletes read notifications older than retention days while preserving newer and unread ones', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-01 00:00:00'));

    $user->notify(new PasswordChanged);
    $oldReadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $oldReadId)->update([
        'read_at' => Date::now()->subDays(31),
        'created_at' => Date::now()->subDays(35),
    ]);

    $user->notify(new PasswordChanged);
    $recentReadId = $user->notifications()->whereNotIn('id', [$oldReadId])->first()->id;
    DB::table('notifications')->where('id', $recentReadId)->update([
        'read_at' => Date::now()->subDays(10),
        'created_at' => Date::now()->subDays(15),
    ]);

    $user->notify(new PasswordChanged);
    $oldUnreadId = $user->notifications()->whereNotIn('id', [$oldReadId, $recentReadId])->first()->id;
    DB::table('notifications')->where('id', $oldUnreadId)->update([
        'read_at' => null,
        'created_at' => Date::now()->subDays(45),
    ]);

    $action = resolve(DeleteNotifications::class);
    $action->handle(retentionDays: 30);

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentReadId)->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeTrue();
});

it('deletes unread notifications older than unread retention days', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-01 00:00:00'));

    $user->notify(new PasswordChanged);
    $oldUnreadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $oldUnreadId)->update([
        'read_at' => null,
        'created_at' => Date::now()->subDays(65),
    ]);

    $user->notify(new PasswordChanged);
    $recentUnreadId = $user->notifications()->whereNotIn('id', [$oldUnreadId])->first()->id;
    DB::table('notifications')->where('id', $recentUnreadId)->update([
        'read_at' => null,
        'created_at' => Date::now()->subDays(20),
    ]);

    $action = resolve(DeleteNotifications::class);
    $action->handle(retentionDays: 0, unreadRetentionDays: 60);

    expect(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentUnreadId)->exists())->toBeTrue();
});

it('prunes notifications in bounded chunks when batch size is lowered', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-01 00:00:00'));

    for ($i = 0; $i < 5; $i++) {
        $user->notify(new PasswordChanged);
    }

    DB::table('notifications')->update([
        'read_at' => Date::now()->subDays(40),
        'created_at' => Date::now()->subDays(45),
    ]);

    expect(DB::table('notifications')->count())->toBe(5);

    $action = resolve(DeleteNotifications::class);
    $action->handle(retentionDays: 30, chunkSize: 2);

    expect(DB::table('notifications')->count())->toBe(0);
});

it('preserves read notifications when retentionDays is 0 and only prunes unread notifications', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-01 00:00:00'));

    $user->notify(new PasswordChanged);
    $oldReadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $oldReadId)->update([
        'read_at' => Date::now()->subDays(100),
    ]);

    $user->notify(new PasswordChanged);
    $oldUnreadId = $user->notifications()->whereNotIn('id', [$oldReadId])->first()->id;
    DB::table('notifications')->where('id', $oldUnreadId)->update([
        'read_at' => null,
        'created_at' => Date::now()->subDays(65),
    ]);

    $action = resolve(DeleteNotifications::class);
    $action->handle(retentionDays: 0, unreadRetentionDays: 60);

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeFalse();
});

it('preserves unread notifications when unreadRetentionDays is 0 and only prunes read notifications', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-01 00:00:00'));

    $user->notify(new PasswordChanged);
    $oldReadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $oldReadId)->update([
        'read_at' => Date::now()->subDays(35),
    ]);

    $user->notify(new PasswordChanged);
    $oldUnreadId = $user->notifications()->whereNotIn('id', [$oldReadId])->first()->id;
    DB::table('notifications')->where('id', $oldUnreadId)->update([
        'read_at' => null,
        'created_at' => Date::now()->subDays(100),
    ]);

    $action = resolve(DeleteNotifications::class);
    $action->handle(retentionDays: 30, unreadRetentionDays: 0);

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeTrue();
});
