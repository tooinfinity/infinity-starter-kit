<?php

declare(strict_types=1);

use App\Actions\AuditTrails\RecordAuditTrail;
use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('user update records a diff and sensitive password or 2FA columns never appear in attribute_changes', function (): void {
    $hasTwoFactor = Schema::hasColumn('users', 'two_factor_secret');

    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'original@example.com',
        'password' => 'secret-password-123',
        ...($hasTwoFactor ? ['two_factor_secret' => '2fa-secret-code'] : []),
    ]);

    $user->update([
        'name' => 'Updated Name',
        'password' => 'new-secret-password',
        ...($hasTwoFactor ? ['two_factor_secret' => 'new-2fa-secret'] : []),
    ]);

    $activity = Activity::query()
        ->where('subject_type', $user->getMorphClass())
        ->where('subject_id', $user->id)
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();

    $changes = $activity->attribute_changes?->toArray() ?? [];
    /** @var array<string, mixed> $attributes */
    $attributes = $changes['attributes'] ?? [];
    /** @var array<string, mixed> $old */
    $old = $changes['old'] ?? [];

    expect($attributes)->toHaveKey('name', 'Updated Name')
        ->and($old)->toHaveKey('name', 'Original Name')
        ->and($attributes)->not->toHaveKey('password')
        ->and($attributes)->not->toHaveKey('remember_token')
        ->and($old)->not->toHaveKey('password')
        ->and($old)->not->toHaveKey('remember_token');

    if ($hasTwoFactor) {
        expect($attributes)->not->toHaveKey('two_factor_secret')
            ->and($old)->not->toHaveKey('two_factor_secret');
    }
});

test('causer is set for authenticated actions and null for console commands', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $action = resolve(RecordAuditTrail::class);
    $authActivity = $action->handle(
        event: AuditEvent::SettingsUpdated,
        newValues: ['app.name' => 'New Auth App'],
    );

    expect($authActivity)->not->toBeNull()
        ->and($authActivity->causer_id)->toBe($actor->id)
        ->and($authActivity->causer_type)->toBe($actor->getMorphClass());

    auth()->logout();

    $consoleActivity = $action->handle(
        event: AuditEvent::SettingsUpdated,
        newValues: ['app.name' => 'Console Updated App'],
    );

    expect($consoleActivity)->not->toBeNull()
        ->and($consoleActivity->causer_id)->toBeNull()
        ->and($consoleActivity->causer_type)->toBeNull();
});

test('recording an explicit audit trail produces exactly one activity log row', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $countBefore = Activity::query()->count();

    $action = resolve(RecordAuditTrail::class);
    $action->handle(
        event: AuditEvent::SettingsUpdated,
        newValues: ['site_name' => 'Test App'],
    );

    $countAfter = Activity::query()->count();

    expect($countAfter - $countBefore)->toBe(1);

    $activity = Activity::query()->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->event)->toBe(AuditEvent::SettingsUpdated->value);
});

test('rolled back transaction leaves no activity row in activity_log', function (): void {
    $action = resolve(RecordAuditTrail::class);
    $user = User::factory()->create();

    $initialCount = Activity::query()->count();

    try {
        DB::transaction(function () use ($action, $user, $initialCount): void {
            $action->handle(
                event: AuditEvent::UserCreated,
                auditable: $user,
                newValues: ['name' => $user->name],
            );

            expect(Activity::query()->count())->toBe($initialCount + 1);

            throw new RuntimeException('Forced rollback');
        });
    } catch (RuntimeException) {
        // Expected rollback
    }

    expect(Activity::query()->count())->toBe($initialCount);
});
