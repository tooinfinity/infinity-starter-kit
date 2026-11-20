<?php

declare(strict_types=1);

use App\Models\AuditTrail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

it('prunes audit rows older than the retention window', function (): void {
    AuditTrail::factory()->create(['created_at' => now()->subDays(400)]);
    $recent = AuditTrail::factory()->create(['created_at' => now()->subDays(5)]);

    Artisan::call('audit:prune', ['--days' => 365]);

    expect(AuditTrail::query()->pluck('id')->all())->toBe([$recent->id]);
});

it('uses configured default retention days when option is omitted', function (): void {
    config(['audit.retention_days' => 90]);

    AuditTrail::factory()->create(['created_at' => now()->subDays(120)]);
    $recent = AuditTrail::factory()->create(['created_at' => now()->subDays(30)]);

    Artisan::call('audit:prune');

    expect(AuditTrail::query()->pluck('id')->all())->toBe([$recent->id]);
});

it('does not prune when retention days is zero or negative', function (): void {
    $record = AuditTrail::factory()->create(['created_at' => now()->subDays(1000)]);

    Artisan::call('audit:prune', ['--days' => 0]);

    expect(AuditTrail::query()->pluck('id')->all())->toBe([$record->id]);
});

it('schedules audit:prune daily with withoutOverlapping and onOneServer', function (): void {
    $schedule = resolve(Schedule::class);

    $event = collect($schedule->events())->first(fn ($event): bool => str_contains($event->command ?? '', 'audit:prune'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and($event->mutex)->not->toBeNull();
});
