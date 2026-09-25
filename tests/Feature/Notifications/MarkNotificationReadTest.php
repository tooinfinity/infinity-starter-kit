<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PasswordChanged;

test('guest cannot mark notification as read', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);

    $notification = $user->notifications()->first();

    $this->patch(route('notifications.mark-read', ['notification' => $notification->id]))
        ->assertRedirect(route('login'));
});

test('authenticated user can mark their notification as read', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);

    $notification = $user->unreadNotifications()->first();

    expect($notification)->not->toBeNull()
        ->and($notification->read_at)->toBeNull();

    $this->actingAs($user)
        ->patch(route('notifications.mark-read', ['notification' => $notification->id]))
        ->assertRedirect();

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();
});

test('marking already read notification is idempotent', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);

    $notification = $user->unreadNotifications()->first();
    $notification->markAsRead();

    $readAt = $notification->fresh()->read_at;

    $this->actingAs($user)
        ->patch(route('notifications.mark-read', ['notification' => $notification->id]))
        ->assertRedirect();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('user cannot mark another users notification as read', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $userB->notify(new PasswordChanged);

    $notificationB = $userB->notifications()->first();

    $this->actingAs($userA)
        ->patch(route('notifications.mark-read', ['notification' => $notificationB->id]))
        ->assertForbidden();

    expect($notificationB->fresh()->read_at)->toBeNull();
});

test('user can mark all their notifications as read', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);
    $user->notify(new PasswordChanged);
    $user->notify(new PasswordChanged);

    expect($user->unreadNotifications()->count())->toBe(3);

    $this->actingAs($user)
        ->patch(route('notifications.mark-all-read'))
        ->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($user->notifications()->count())->toBe(3);
});

test('mark all read does not affect another users notifications', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $userA->notify(new PasswordChanged);
    $userB->notify(new PasswordChanged);

    $this->actingAs($userA)
        ->patch(route('notifications.mark-all-read'))
        ->assertRedirect();

    expect($userA->unreadNotifications()->count())->toBe(0)
        ->and($userB->unreadNotifications()->count())->toBe(1);
});
