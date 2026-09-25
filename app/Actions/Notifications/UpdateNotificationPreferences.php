<?php

declare(strict_types=1);

namespace App\Actions\Notifications;

use App\Enums\NotificationType;
use App\Models\NotificationPreference;
use App\Models\User;

final readonly class UpdateNotificationPreferences
{
    /**
     * @param  array<string, array{database_enabled: bool}>  $preferences
     */
    public function handle(User $user, array $preferences): void
    {
        foreach ($preferences as $type => $settings) {
            $notificationType = NotificationType::from($type);

            if ($notificationType->isMandatory()) {
                continue;
            }

            NotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type' => $notificationType->value,
                ],
                [
                    'database_enabled' => $settings['database_enabled'],
                ],
            );
        }
    }
}
