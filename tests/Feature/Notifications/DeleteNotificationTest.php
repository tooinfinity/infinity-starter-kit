<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PasswordChanged;

test('guest cannot delete notification', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);

    $notification = $user->notifications()->first();

    $this->delete(route('notifications.destroy', ['notification' => $notification->id]))
        ->assertRedirect(route('login'));
});

test('authenticated user can delete their notification', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);

    $notification = $user->notifications()->first();

    expect($user->notifications()->count())->toBe(1);

    $this->actingAs($user)
        ->delete(route('notifications.destroy', ['notification' => $notification->id]))
        ->assertRedirect();

    expect($user->notifications()->count())->toBe(0);
});

test('user cannot delete another users notification', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $userB->notify(new PasswordChanged);

    $notificationB = $userB->notifications()->first();

    $this->actingAs($userA)
        ->delete(route('notifications.destroy', ['notification' => $notificationB->id]))
        ->assertForbidden();

    expect($userB->notifications()->count())->toBe(1);
});
