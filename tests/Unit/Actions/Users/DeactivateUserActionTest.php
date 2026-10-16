<?php

declare(strict_types=1);

use App\Actions\Users\DeactivateUserAction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('DeactivateUserAction deactivates user', function (): void {
    $user = User::factory()->create(['is_active' => true]);

    $action = resolve(DeactivateUserAction::class);
    $action->handle($user);

    expect($user->fresh()->is_active)->toBeFalse();
});

test('DeactivateUserAction prevents user from deactivating self', function (): void {
    $user = User::factory()->create(['is_active' => true]);

    $action = resolve(DeactivateUserAction::class);

    expect(fn () => $action->handle($user, $user))
        ->toThrow(ValidationException::class);
});
