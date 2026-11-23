<?php

declare(strict_types=1);

use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('audit trail is rolled back if database transaction fails', function (): void {
    $action = resolve(RecordAuditTrail::class);
    $user = User::factory()->create();

    expect(AuditTrail::query()->count())->toBe(0)
        ->and(Activity::query()->count())->toBe(0);

    try {
        DB::transaction(function () use ($action, $user): void {
            $action->handle(
                event: AuditEvent::UserCreated,
                auditable: $user,
                newValues: ['name' => $user->name],
            );

            // Verify it was created within the transaction boundary
            expect(AuditTrail::query()->count())->toBe(1)
                ->and(Activity::query()->count())->toBe(1);

            // Force a transaction failure
            throw new RuntimeException('Simulated database failure');
        });
    } catch (RuntimeException $runtimeException) {
        expect($runtimeException->getMessage())->toBe('Simulated database failure');
    }

    // Verify the audit trail was rolled back
    expect(AuditTrail::query()->count())->toBe(0)
        ->and(Activity::query()->count())->toBe(0);
});

test('both activity and legacy audit trail are rolled back if secondary write fails', function (): void {
    $action = resolve(RecordAuditTrail::class);
    $user = User::factory()->create();

    AuditTrail::creating(static function (): never {
        throw new RuntimeException('Secondary write failed');
    });

    try {
        $action->handle(
            event: AuditEvent::UserCreated,
            auditable: $user,
            newValues: ['name' => $user->name],
        );
    } catch (RuntimeException $runtimeException) {
        expect($runtimeException->getMessage())->toBe('Secondary write failed');
    }

    expect(Activity::query()->count())->toBe(0)
        ->and(AuditTrail::query()->count())->toBe(0);
});
