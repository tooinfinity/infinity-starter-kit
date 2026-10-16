<?php

declare(strict_types=1);

use App\Actions\Notifications\MarkNotificationAsRead;
use App\Models\User;
use App\Notifications\PasswordChanged;

test('MarkNotificationAsRead marks notification as read', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);

    $notification = $user->unreadNotifications()->first();

    $action = resolve(MarkNotificationAsRead::class);
    $action->handle($user, $notification->id);

    $notification->refresh();
    expect($notification->read_at)->not->toBeNull();
});
