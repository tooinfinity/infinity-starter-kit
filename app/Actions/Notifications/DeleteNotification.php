<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;

/* @chisel-notifications */

final readonly class DeleteNotification
{
    public function handle(User $user, string $notificationId): void
    {
        $user->notifications()->findOrFail($notificationId)->delete();
    }
}

/* @end-chisel-notifications */
