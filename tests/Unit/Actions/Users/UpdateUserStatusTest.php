<?php

declare(strict_types=1);

use App\Actions\Users\UpdateUserStatus;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('UpdateUserStatus activates inactive user', function (): void {
    $user = User::factory()->create(['is_active' => false]);

    $action = resolve(UpdateUserStatus::class);
    $action->handle($user, true);

    expect($user->fresh()->is_active)->toBeTrue();
});

test('UpdateUserStatus deactivates user', function (): void {
    $user = User::factory()->create(['is_active' => true]);

    $action = resolve(UpdateUserStatus::class);
    $action->handle($user, false);

    expect($user->fresh()->is_active)->toBeFalse();
});

test('UpdateUserStatus prevents user from deactivating self', function (): void {
    $user = User::factory()->create(['is_active' => true]);

    $action = resolve(UpdateUserStatus::class);

    expect(fn () => $action->handle($user, false, $user))
        ->toThrow(ValidationException::class);
});
