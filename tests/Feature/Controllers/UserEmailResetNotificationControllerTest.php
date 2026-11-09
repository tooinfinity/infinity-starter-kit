<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

it('renders forgot password page', function (): void {
    $response = $this->fromRoute('home')
        ->get(route('password.request'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('user-email-reset-notification/create')
            ->has('status'));
});

it('may send password reset notification', function (): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => 'test@example.com',
        ]);

    $response->assertRedirectToRoute('password.request')
        ->assertSessionHas('status', 'A reset link will be sent if the account exists.');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('returns generic message for non-existent email', function (): void {
    Notification::fake();

    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => 'nonexistent@example.com',
        ]);

    $response->assertRedirectToRoute('password.request')
        ->assertSessionHas('status', 'A reset link will be sent if the account exists.');

    Notification::assertNothingSent();
});

it('requires email', function (): void {
    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), []);

    $response->assertRedirectToRoute('password.request')
        ->assertSessionHasErrors('email');
});

it('requires valid email format', function (): void {
    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => 'not-an-email',
        ]);

    $response->assertRedirectToRoute('password.request')
        ->assertSessionHasErrors('email');
});

it('redirects authenticated users away from forgot password', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('password.request'));

    $response->assertRedirectToRoute('dashboard');
});

it('sends password reset notification with mixed-case and whitespace-padded email', function (string $emailInput): void {
    Notification::fake();

    $user = User::factory()->create([
        'email' => 'john@example.com',
    ]);

    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => $emailInput,
        ]);

    $response->assertRedirectToRoute('password.request')
        ->assertSessionHas('status', 'A reset link will be sent if the account exists.');

    Notification::assertSentTo($user, ResetPassword::class);
})->with([
    'mixed case' => 'John@Example.com',
    'uppercase' => 'JOHN@EXAMPLE.COM',
    'surrounding whitespace' => '   john@example.com   ',
    'mixed case with whitespace' => '  John@Example.COM  ',
]);

it('throttles password reset link requests by ip after too many requests', function (): void {
    Notification::fake();

    for ($i = 0; $i < 5; $i++) {
        $this->fromRoute('password.request')
            ->post(route('password.email'), [
                'email' => "user{$i}@example.com",
            ])
            ->assertRedirectToRoute('password.request');
    }

    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => 'another@example.com',
        ]);

    $response->assertTooManyRequests();
});

it('throttles password reset link requests by email after too many requests across different ips', function (): void {
    Notification::fake();

    for ($i = 0; $i < 5; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => "192.168.1.{$i}"])
            ->fromRoute('password.request')
            ->post(route('password.email'), [
                'email' => 'targeted@example.com',
            ])
            ->assertRedirectToRoute('password.request');
    }

    $response = $this->withServerVariables(['REMOTE_ADDR' => '192.168.1.99'])
        ->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => 'targeted@example.com',
        ]);

    $response->assertTooManyRequests();
});

it('respects configurable password reset link rate limit', function (): void {
    config()->set('fortify.rate_limits.forgot_password', 2);
    Notification::fake();

    for ($i = 0; $i < 2; $i++) {
        $this->fromRoute('password.request')
            ->post(route('password.email'), [
                'email' => "user{$i}@example.com",
            ])
            ->assertRedirectToRoute('password.request');
    }

    $response = $this->fromRoute('password.request')
        ->post(route('password.email'), [
            'email' => 'another@example.com',
        ]);

    $response->assertTooManyRequests();
});

it('counts invalid password reset link requests once per limiter', function (?string $email): void {
    for ($i = 0; $i < 5; $i++) {
        $this->fromRoute('password.request')
            ->post(route('password.email'), ['email' => $email])
            ->assertRedirectToRoute('password.request')
            ->assertSessionHasErrors('email');
    }

    $this->fromRoute('password.request')
        ->post(route('password.email'), ['email' => $email])
        ->assertTooManyRequests();
})->with([null, '', '127.0.0.1']);
