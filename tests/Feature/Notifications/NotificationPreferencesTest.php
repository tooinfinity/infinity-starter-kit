<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/* @chisel-notifications */

test('guest cannot view notification preferences', function (): void {
    $this->get(route('notification-preferences.edit'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view notification preferences page', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('notification-preferences.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('settings/notifications/edit')
            ->has('preferences', 3)
            ->where('preferences.0.type', 'security')
            ->where('preferences.0.mandatory', true)
            ->where('preferences.0.database_enabled', true)
        );
});

test('user can disable non-mandatory notification preference', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('notification-preferences.update'), [
            'preferences' => [
                'user_management' => ['database_enabled' => false],
                'system' => ['database_enabled' => false],
            ],
        ])
        ->assertRedirect();

    $pref = NotificationPreference::query()->where('user_id', $user->id)
        ->where('notification_type', NotificationType::UserManagement)
        ->first();

    expect($pref)->not->toBeNull()
        ->and($pref->database_enabled)->toBeFalse();
});

test('cannot disable mandatory notification type', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->put(route('notification-preferences.update'), [
            'preferences' => [
                'security' => ['database_enabled' => false],
            ],
        ])
        ->assertRedirect();

    $pref = NotificationPreference::query()->where('user_id', $user->id)
        ->where('notification_type', NotificationType::Security)
        ->first();

    expect($pref)->toBeNull();
});

test('preferences are scoped per user', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA)
        ->put(route('notification-preferences.update'), [
            'preferences' => [
                'user_management' => ['database_enabled' => false],
            ],
        ]);

    $this->actingAs($userB)
        ->get(route('notification-preferences.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('settings/notifications/edit')
            ->where('preferences.1.database_enabled', true)
        );
});

test('notification preference belongs to a user', function (): void {
    $user = User::factory()->create();
    $preference = NotificationPreference::query()->create([
        'user_id' => $user->id,
        'notification_type' => NotificationType::System,
        'database_enabled' => true,
    ]);

    expect($preference->user)->toBeInstanceOf(User::class)
        ->and($preference->user->id)->toBe($user->id);
});

/* @end-chisel-notifications */
