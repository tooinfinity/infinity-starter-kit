<?php

declare(strict_types=1);

use App\Actions\Users\DeleteUserAction;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('DeleteUserAction deletes user', function (): void {
    $user = User::factory()->create();

    $action = resolve(DeleteUserAction::class);
    $action->handle($user);

    expect(User::query()->find($user->id))->toBeNull();
});

test('DeleteUserAction prevents user from deleting self', function (): void {
    $user = User::factory()->create();

    $action = resolve(DeleteUserAction::class);

    expect(fn () => $action->handle($user, $user))
        ->toThrow(ValidationException::class);
});
