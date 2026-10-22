<?php

declare(strict_types=1);

use App\Actions\Users\ActivateUserAction;
use App\Actions\Users\ChangeUserPasswordAction;
use App\Actions\Users\DeactivateUserAction;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Notifications\UserActivated;
use App\Notifications\UserDeactivated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\DB;

it('queues jobs after the transaction commits', function (): void {
    foreach (['database', 'redis', 'sqs', 'beanstalkd'] as $connection) {
        expect(config("queue.connections.{$connection}.after_commit"))->toBeTrue();
    }
});

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
    $action = resolve(ActivateUserAction::class);

    try {
        DB::transaction(function () use ($user, $action): void {
            $action->handle($user);
            throw new RuntimeException('Simulated rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('jobs')->count())->toBe(0);
});

it('dispatches queued notification after user activation transaction commits', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->inactive()->create();
    $action = resolve(ActivateUserAction::class);

    $action->handle($user);

    expect(DB::table('jobs')->count())->toBe(1);
});

it('leaves no queued jobs when a deactivate user action is rolled back', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->active()->create();
    $action = resolve(DeactivateUserAction::class);

    try {
        DB::transaction(function () use ($user, $action): void {
            $action->handle($user);
            throw new RuntimeException('Simulated rollback');
        });
    } catch (RuntimeException) {
    }

    expect(DB::table('jobs')->count())->toBe(0);
});

it('dispatches queued notification after user deactivation transaction commits', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->active()->create();
    $action = resolve(DeactivateUserAction::class);

    $action->handle($user);

    expect(DB::table('jobs')->count())->toBe(1);
});

it('leaves no queued jobs when a change password action is rolled back', function (): void {
    config(['queue.default' => 'database']);
    $user = User::factory()->create();
    $action = resolve(ChangeUserPasswordAction::class);

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
    $action = resolve(ChangeUserPasswordAction::class);

    $action->handle($user, 'new-password-123');

    expect(DB::table('jobs')->count())->toBe(1);
});
