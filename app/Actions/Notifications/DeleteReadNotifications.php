<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Models\User;

final readonly class DeleteReadNotifications
{
    public function handle(User $user): void
    {
        $user->readNotifications()->delete();
    }
}
