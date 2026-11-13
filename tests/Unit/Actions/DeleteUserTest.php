<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Models\User;

it('deletes user account and removes record from database', function (): void {
    $user = User::factory()->create();

    $action = resolve(DeleteUser::class);

    $action->handle($user);

    expect(User::query()->find($user->id))->toBeNull()
        ->and($user->exists)->toBeFalse();
});
