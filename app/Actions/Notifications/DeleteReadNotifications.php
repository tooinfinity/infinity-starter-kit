<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;

/* @chisel-notifications */

final readonly class DeleteReadNotifications
{
    public function handle(User $user): void
    {
        $user->readNotifications()->delete();
    }
}

/* @end-chisel-notifications */
