<?php

declare(strict_types=1);

use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/* @chisel-audit-trails */

test('audit trail is rolled back if database transaction fails', function (): void {
    $action = resolve(RecordAuditTrail::class);
    $user = User::factory()->create();

    expect(AuditTrail::query()->count())->toBe(0);

    try {
        DB::transaction(function () use ($action, $user): void {
            $action->handle(
                event: AuditEvent::UserCreated,
                auditable: $user,
                newValues: ['name' => $user->name],
            );

            // Verify it was created within the transaction boundary
            expect(AuditTrail::query()->count())->toBe(1);

            // Force a transaction failure
            throw new RuntimeException('Simulated database failure');
        });
    } catch (RuntimeException $runtimeException) {
        expect($runtimeException->getMessage())->toBe('Simulated database failure');
    }

    // Verify the audit trail was rolled back
    expect(AuditTrail::query()->count())->toBe(0);
});

/* @end-chisel-audit-trails */
