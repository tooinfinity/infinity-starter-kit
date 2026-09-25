<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PasswordChanged;
use Inertia\Testing\AssertableInertia as Assert;

test('guest cannot view notifications', function (): void {
    $this->get(route('notifications.index'))
        ->assertRedirect(route('login'));
});

test('authenticated user can view their notifications page', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('notifications/index')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.title', 'Password Changed')
        );
});

test('notifications are strictly scoped to the authenticated user', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $userA->notify(new PasswordChanged);
    $userB->notify(new PasswordChanged);

    $this->actingAs($userA)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('notifications/index')
            ->has('notifications.data', 1)
        );
});

test('notifications are paginated with 15 per page', function (): void {
    $user = User::factory()->create();

    for ($i = 0; $i < 20; $i++) {
        $user->notify(new PasswordChanged);
    }

    $this->actingAs($user)
        ->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('notifications/index')
            ->has('notifications.data', 15)
            ->where('notifications.total', 20)
            ->where('notifications.last_page', 2)
        );
});
