<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

test('notifications:prune deletes read notifications older than default configured days and keeps newer and unread ones', function (): void {
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

    $this->artisan('notifications:prune');

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentReadId)->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('id', $recentUnreadId)->exists())->toBeTrue();
});

test('notifications:prune deletes read notifications based on custom configured retention days', function (): void {
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

    $this->artisan('notifications:prune');

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentReadId)->exists())->toBeTrue();
});

test('notifications:prune does not delete read notifications when retention is disabled', function (): void {
    config(['notifications.retention_days' => 0]);

    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-03 00:00:00'));

    $user->notify(new PasswordChanged);
    $oldReadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $oldReadId)->update([
        'read_at' => Date::now()->subDays(100),
    ]);

    $this->artisan('notifications:prune');

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeTrue();
});

test('notifications:prune deletes unread notifications when unread retention is configured', function (): void {
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

    $this->artisan('notifications:prune');

    expect(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentUnreadId)->exists())->toBeTrue();
});

test('notifications:prune command is scheduled daily with withoutOverlapping and onOneServer', function (): void {
    $schedule = resolve(Schedule::class);

    $event = collect($schedule->events())->first(fn ($event): bool => str_contains($event->command ?? '', 'notifications:prune'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->mutex)->not->toBeNull();
});

test('notifications:prune command prunes read notifications older than specified days', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-05 00:00:00'));

    $user->notify(new PasswordChanged);
    $oldReadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $oldReadId)->update([
        'read_at' => Date::now()->subDays(8),
    ]);

    $user->notify(new PasswordChanged);
    $recentReadId = $user->notifications()->where('id', '!=', $oldReadId)->first()->id;
    DB::table('notifications')->where('id', $recentReadId)->update([
        'read_at' => Date::now()->subDays(5),
    ]);

    $this->artisan('notifications:prune', ['--days' => 7])
        ->assertSuccessful();

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentReadId)->exists())->toBeTrue();
});

test('notifications:prune command prunes unread notifications older than specified unread days', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-06 00:00:00'));

    $user->notify(new PasswordChanged);
    $oldUnreadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $oldUnreadId)->update([
        'read_at' => null,
        'created_at' => Date::now()->subDays(65),
    ]);

    $user->notify(new PasswordChanged);
    $recentUnreadId = $user->notifications()->where('id', '!=', $oldUnreadId)->first()->id;
    DB::table('notifications')->where('id', $recentUnreadId)->update([
        'read_at' => null,
        'created_at' => Date::now()->subDays(20),
    ]);

    $this->artisan('notifications:prune', ['--unread-days' => 60])
        ->assertSuccessful();

    expect(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $recentUnreadId)->exists())->toBeTrue();
});

test('notifications:prune command warns when retention is disabled', function (): void {
    $user = User::factory()->create();

    $user->notify(new PasswordChanged);

    $readId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $readId)->update([
        'read_at' => Date::now()->subDays(100),
    ]);

    $this->artisan('notifications:prune', ['--days' => 0])
        ->expectsOutputToContain('Notification pruning is disabled')
        ->assertSuccessful();

    expect(DB::table('notifications')->where('id', $readId)->exists())->toBeTrue();
});

test('notifications:prune command prunes in bounded chunks when chunk size is specified', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-07 00:00:00'));

    for ($i = 0; $i < 5; $i++) {
        $user->notify(new PasswordChanged);
    }

    DB::table('notifications')->update([
        'read_at' => Date::now()->subDays(40),
    ]);

    expect(DB::table('notifications')->count())->toBe(5);

    $this->artisan('notifications:prune', ['--days' => 30, '--chunk' => 2])
        ->assertSuccessful();

    expect(DB::table('notifications')->count())->toBe(0);
});

test('notifications:prune command rejects invalid explicit days and causes no deletion', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-08 00:00:00'));

    $user->notify(new PasswordChanged);
    $readId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $readId)->update([
        'read_at' => Date::now()->subDays(60),
    ]);

    $this->artisan('notifications:prune', ['--days' => '365d'])
        ->expectsOutputToContain('The --days option must be an integer greater than or equal to 0.')
        ->assertFailed();

    expect(DB::table('notifications')->where('id', $readId)->exists())->toBeTrue();
});

test('notifications:prune command rejects invalid explicit unread-days and causes no deletion', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-09 00:00:00'));

    $user->notify(new PasswordChanged);
    $unreadId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $unreadId)->update([
        'read_at' => null,
        'created_at' => Date::now()->subDays(60),
    ]);

    $this->artisan('notifications:prune', ['--unread-days' => 'invalid'])
        ->expectsOutputToContain('The --unread-days option must be an integer greater than or equal to 0.')
        ->assertFailed();

    expect(DB::table('notifications')->where('id', $unreadId)->exists())->toBeTrue();
});

test('notifications:prune command rejects invalid explicit chunk size and causes no deletion', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-10 00:00:00'));

    $user->notify(new PasswordChanged);
    $readId = $user->notifications()->first()->id;
    DB::table('notifications')->where('id', $readId)->update([
        'read_at' => Date::now()->subDays(60),
    ]);

    $this->artisan('notifications:prune', ['--chunk' => 0])
        ->expectsOutputToContain('The --chunk option must be an integer greater than 0.')
        ->assertFailed();

    expect(DB::table('notifications')->where('id', $readId)->exists())->toBeTrue();
});

test('notifications:prune command preserves read notifications when days is 0 and unread retention is set', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-11 00:00:00'));

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

    $this->artisan('notifications:prune', ['--days' => 0, '--unread-days' => 60])
        ->assertSuccessful();

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeTrue()
        ->and(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeFalse();
});

test('notifications:prune command preserves unread notifications when unread-days is 0', function (): void {
    $user = User::factory()->create();

    $this->travelTo(Date::parse('2026-06-12 00:00:00'));

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

    $this->artisan('notifications:prune', ['--days' => 30, '--unread-days' => 0])
        ->assertSuccessful();

    expect(DB::table('notifications')->where('id', $oldReadId)->exists())->toBeFalse()
        ->and(DB::table('notifications')->where('id', $oldUnreadId)->exists())->toBeTrue();
});
