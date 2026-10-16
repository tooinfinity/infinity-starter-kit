<?php

declare(strict_types=1);

use App\Actions\Notifications\UpdateNotificationPreferences;
use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;

test('UpdateNotificationPreferences updates non-mandatory notification preferences', function (): void {
    $user = User::factory()->create();

    $action = resolve(UpdateNotificationPreferences::class);
    $action->handle($user, [
        NotificationType::UserManagement->value => [
            'database_enabled' => false,
        ],
    ]);

    $pref = NotificationPreference::query()
        ->where('user_id', $user->id)
        ->where('notification_type', NotificationType::UserManagement->value)
        ->first();

    expect($pref)->not->toBeNull()
        ->and($pref->database_enabled)->toBeFalse();
});
