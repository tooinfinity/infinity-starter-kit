<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/* @chisel-notifications */
test('notifications:prune command is scheduled with withoutOverlapping and onOneServer', function (): void {
    $schedule = resolve(Schedule::class);

    $event = collect($schedule->events())->first(
        fn (Event $event): bool => str_contains($event->command ?? '', 'notifications:prune')
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->mutex)->not->toBeNull();
});
/* @end-chisel-notifications */

/* @chisel-audit-trails */
test('audit:prune command is scheduled with withoutOverlapping and onOneServer', function (): void {
    $schedule = resolve(Schedule::class);

    $event = collect($schedule->events())->first(
        fn (Event $event): bool => str_contains($event->command ?? '', 'audit:prune')
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->mutex)->not->toBeNull();
});
/* @end-chisel-audit-trails */
