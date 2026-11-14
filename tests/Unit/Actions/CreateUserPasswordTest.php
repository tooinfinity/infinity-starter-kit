<?php

declare(strict_types=1);

use App\Actions\CreateUserPassword;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

it('resets user password with valid token and dispatches password reset event', function (): void {
    Event::fake([PasswordReset::class]);

    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $token = Password::createToken($user);

    $action = resolve(CreateUserPassword::class);

    $status = $action->handle([
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ], 'new-password');

    expect($status)->toBe(Password::PASSWORD_RESET)
        ->and(Hash::check('new-password', $user->refresh()->password))->toBeTrue();

    Event::assertDispatched(PasswordReset::class);
});

it('fails password reset with error status for invalid credentials', function (array $overridePayload, string $expectedStatus): void {
    $user = User::factory()->create([
        'email' => 'test@example.com',
    ]);

    $action = resolve(CreateUserPassword::class);

    $payload = array_merge([
        'email' => $user->email,
        'token' => Password::createToken($user),
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ], $overridePayload);

    $status = $action->handle($payload, 'new-password');

    expect($status)->toBe($expectedStatus);
})->with([
    'invalid token' => [['token' => 'invalid-token'], Password::INVALID_TOKEN],
    'invalid user' => [['email' => 'nonexistent@example.com'], Password::INVALID_USER],
]);

it('updates remember token when resetting password', function (): void {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'remember_token' => 'old-token',
    ]);

    $token = Password::createToken($user);

    $action = resolve(CreateUserPassword::class);

    $action->handle([
        'email' => $user->email,
        'token' => $token,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ], 'new-password');

    expect($user->refresh()->remember_token)->not->toBe('old-token')
        ->and($user->remember_token)->not->toBeNull();
});
