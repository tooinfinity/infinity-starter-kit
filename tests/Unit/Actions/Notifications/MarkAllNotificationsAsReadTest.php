<?php

declare(strict_types=1);

use App\Actions\Notifications\MarkAllNotificationsAsRead;
use App\Models\User;
use App\Notifications\PasswordChanged;

test('MarkAllNotificationsAsRead marks all unread notifications as read', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);
    $user->notify(new PasswordChanged);

    expect($user->unreadNotifications()->count())->toBe(2);

    $action = resolve(MarkAllNotificationsAsRead::class);
    $action->handle($user);

    expect($user->unreadNotifications()->count())->toBe(0);
});
