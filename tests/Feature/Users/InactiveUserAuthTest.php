<?php

declare(strict_types=1);

use App\Models\User;

it('denies authentication for inactive users', function (): void {
    $inactiveUser = User::factory()->withoutTwoFactor()->inactive()->create([
        'email' => 'inactive@example.com',
        'password' => 'password123',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'inactive@example.com',
        'password' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email']);
    $this->assertGuest();
});

it('allows authentication for active users', function (): void {
    $activeUser = User::factory()->withoutTwoFactor()->active()->create([
        'email' => 'active@example.com',
        'password' => 'password123',
    ]);

    $response = $this->post(route('login.store'), [
        'email' => 'active@example.com',
        'password' => 'password123',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($activeUser);
});

it('logs out logged-in user when account becomes inactive', function (): void {
    $user = User::factory()->withoutTwoFactor()->active()->create();

    $this->actingAs($user);

    $user->forceFill(['is_active' => false])->save();

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $this->assertGuest();
});
