<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PasswordChanged;
use Inertia\Testing\AssertableInertia as Assert;

/* @chisel-notifications */

test('guest has unreadCount 0 and empty recent in shared props', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('notifications.unreadCount', 0)
            ->where('notifications.recent', [])
        );
});

test('authenticated user receives accurate unreadCount and recent notifications in shared props', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);
    $user->notify(new PasswordChanged);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('notifications.unreadCount', 2)
            ->has('notifications.recent', 2)
            ->where('notifications.recent.0.title', 'Password Changed')
        );
});

test('unreadCount updates when notification is marked as read', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);

    $notification = $user->unreadNotifications()->first();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('notifications.unreadCount', 1)
        );

    $this->actingAs($user)
        ->patch(route('notifications.mark-read', ['notification' => $notification->id]));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('notifications.unreadCount', 0)
        );
});

/* @end-chisel-notifications */
