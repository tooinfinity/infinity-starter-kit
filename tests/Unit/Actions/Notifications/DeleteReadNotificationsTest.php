<?php

declare(strict_types=1);

use App\Actions\Notifications\DeleteReadNotifications;
use App\Models\User;
use App\Notifications\PasswordChanged;

it('deletes read notifications for user while preserving unread ones', function (): void {
    $user = User::factory()->create();

    $user->notify(new PasswordChanged);
    $user->notify(new PasswordChanged);

    expect($user->notifications()->count())->toBe(2);

    $notificationToRead = $user->unreadNotifications()->first();
    expect($notificationToRead)->not->toBeNull();
    $notificationToRead->markAsRead();

    expect($user->readNotifications()->count())->toBe(1)
        ->and($user->unreadNotifications()->count())->toBe(1);

    $action = resolve(DeleteReadNotifications::class);
    $action->handle($user);

    expect($user->notifications()->count())->toBe(1)
        ->and($user->readNotifications()->count())->toBe(0)
        ->and($user->unreadNotifications()->count())->toBe(1);
});
