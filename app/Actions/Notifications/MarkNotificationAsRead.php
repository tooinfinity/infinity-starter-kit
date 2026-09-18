<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/* @chisel-notifications */

final readonly class MarkNotificationAsRead
{
    public function handle(User $user, string $notificationId): void
    {
        $notification = $user->notifications()->findOrFail($notificationId);

        /** @var DatabaseNotification $notification */
        $notification->markAsRead();
    }
}

/* @end-chisel-notifications */
