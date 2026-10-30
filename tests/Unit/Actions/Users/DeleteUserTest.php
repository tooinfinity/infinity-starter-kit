<?php

declare(strict_types=1);

use App\Actions\Users\DeleteUser;
use App\Models\User;
use Illuminate\Validation\ValidationException;

test('DeleteUser deletes user', function (): void {
    $user = User::factory()->create();

    $action = resolve(DeleteUser::class);
    $action->handle($user);

    expect(User::query()->find($user->id))->toBeNull();
});

test('DeleteUser prevents user from deleting self', function (): void {
    $user = User::factory()->create();

    $action = resolve(DeleteUser::class);

    expect(fn () => $action->handle($user, $user))
        ->toThrow(ValidationException::class);
});
