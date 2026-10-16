<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;

test('notification preference can be created and casts attributes correctly', function (): void {
    $user = User::factory()->create();

    $preference = NotificationPreference::factory()->create([
        'user_id' => $user->id,
        'notification_type' => NotificationType::UserManagement,
        'database_enabled' => true,
    ]);

    expect($preference->id)->toBeString()
        ->and($preference->user_id)->toBe($user->id)
        ->and($preference->notification_type)->toBe(NotificationType::UserManagement)
        ->and($preference->database_enabled)->toBeTrue();
});

test('notification preference belongs to user', function (): void {
    $user = User::factory()->create();

    $preference = NotificationPreference::factory()->create([
        'user_id' => $user->id,
    ]);

    expect($preference->user)->toBeInstanceOf(User::class)
        ->and($preference->user->id)->toBe($user->id);
});
