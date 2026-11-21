<?php

declare(strict_types=1);

use App\Actions\AuditTrails\DeleteAuditTrails;
use App\Models\Activity;

test('DeleteAuditTrails deletes records older than given days', function (): void {
    $old = Activity::factory()->create(['created_at' => now()->subDays(40)]);
    $recent = Activity::factory()->create(['created_at' => now()->subDays(10)]);

    $action = new DeleteAuditTrails;
    $action->handle(30);

    expect(Activity::query()->pluck('id')->all())->toBe([$recent->id]);
});
