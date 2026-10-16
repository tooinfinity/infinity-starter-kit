<?php

declare(strict_types=1);

use App\Actions\Notifications\DeleteNotification;
use App\Models\User;
use App\Notifications\PasswordChanged;

test('DeleteNotification removes specified notification for user', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);

    $notification = $user->notifications()->first();

    $action = resolve(DeleteNotification::class);
    $action->handle($user, $notification->id);

    expect($user->notifications()->count())->toBe(0);
});
