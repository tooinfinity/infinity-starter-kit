<?php

declare(strict_types=1);

namespace Tests\Unit\Queries\AuditTrails;

use App\Models\Activity;
use App\Models\AuditTrail;
use App\Queries\AuditTrails\HistoricalAuditTrailQuery;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

test('historical audit trail query combines activity_log and audit_trails', function (): void {
    Activity::query()->delete();
    AuditTrail::query()->delete();

    $activity = Activity::factory()->create([
        'description' => 'Spatie Activity',
    ]);

    $audit = AuditTrail::factory()->create();

    $results = HistoricalAuditTrailQuery::build()->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id')->all())->toContain($activity->id, $audit->id);
});

test('historical audit trail query falls back cleanly when audit_trails table does not exist', function (): void {
    Schema::dropIfExists('audit_trails');

    Activity::query()->create([
        'description' => 'Test activity',
    ]);

    $results = HistoricalAuditTrailQuery::build()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->description)->toBe('Test activity');

    // Restore table for subsequent tests
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_09_20_000000_create_audit_trails_table.php');
    $migration->up();
    /** @var Migration $actorFieldsMigration */
    $actorFieldsMigration = require database_path('migrations/2026_10_20_234750_add_actor_fields_to_audit_trails_table.php');
    $actorFieldsMigration->up();
});

test('historical audit trail query correctly decodes legacy old_values and new_values from historical audit records', function (): void {
    $audit = AuditTrail::factory()->create([
        'old_values' => ['name' => 'Old Name'],
        'new_values' => ['name' => 'New Name'],
    ]);

    $record = HistoricalAuditTrailQuery::build()->where('id', $audit->id)->firstOrFail();

    expect($record->old_values)->toBe(['name' => 'Old Name'])
        ->and($record->new_values)->toBe(['name' => 'New Name']);
});
