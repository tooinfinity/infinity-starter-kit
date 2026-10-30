<?php

declare(strict_types=1);

use App\Actions\Users\UpdateUserPassword;
use App\Actions\Users\UpdateUserStatus;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Notifications\UserActivated;
use App\Notifications\UserDeactivated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\DB;

it('implements ShouldQueueAfterCommit on user notifications', function (): void {
    expect(new PasswordChanged)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and(new PasswordChanged)->toBeInstanceOf(ShouldQueue::class)
        ->and(new UserActivated)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and(new UserActivated)->toBeInstanceOf(ShouldQueue::class)
        ->and(new UserDeactivated)->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and(new UserDeactivated)->toBeInstanceOf(ShouldQueue::class);
});

it('leaves no queued jobs when an activate user action is rolled back', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->inactive()->create();
    $action = resolve(UpdateUserStatus::class);

    try {
        DB::transaction(function () use ($user, $action): void {
            $action->handle($user, true);
            throw new RuntimeException('Simulated rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('jobs')->count())->toBe(0);
});

it('dispatches queued notification after user activation transaction commits', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->inactive()->create();
    $action = resolve(UpdateUserStatus::class);

    $action->handle($user, true);

    expect(DB::table('jobs')->count())->toBe(1);
});

it('leaves no queued jobs when a deactivate user action is rolled back', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->active()->create();
    $action = resolve(UpdateUserStatus::class);

    try {
        DB::transaction(function () use ($user, $action): void {
            $action->handle($user, false);
            throw new RuntimeException('Simulated rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('jobs')->count())->toBe(0);
});

it('dispatches queued notification after user deactivation transaction commits', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->active()->create();
    $action = resolve(UpdateUserStatus::class);

    $action->handle($user, false);

    expect(DB::table('jobs')->count())->toBe(1);
});

it('leaves no queued jobs when a change password action is rolled back', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->create();
    $action = resolve(UpdateUserPassword::class);

    try {
        DB::transaction(function () use ($user, $action): void {
            $action->handle($user, 'new-password-123');
            throw new RuntimeException('Simulated rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('jobs')->count())->toBe(0);
});

it('dispatches queued notification after password change transaction commits', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->create();
    $action = resolve(UpdateUserPassword::class);

    $action->handle($user, 'new-password-123');

    expect(DB::table('jobs')->count())->toBe(1);
});

it('sends nothing when a notification is dispatched inside a transaction that rolls back', function (string $notificationClass): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->create();

    try {
        DB::transaction(function () use ($user, $notificationClass): void {
            $user->notify(new $notificationClass);
            throw new RuntimeException('Simulated rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('jobs')->count())->toBe(0);
})->with([
    PasswordChanged::class,
    UserActivated::class,
    UserDeactivated::class,
]);

it('dispatches notification after transaction commits', function (string $notificationClass): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->create();

    DB::transaction(function () use ($user, $notificationClass): void {
        $user->notify(new $notificationClass);
    });

    expect(DB::table('jobs')->count())->toBe(1);
})->with([
    PasswordChanged::class,
    UserActivated::class,
    UserDeactivated::class,
]);
