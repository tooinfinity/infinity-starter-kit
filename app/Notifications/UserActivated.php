<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class UserActivated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array{type: string, title: string, body: string, icon: string}
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => NotificationType::UserManagement->value,
            'title' => __('notifications.user_activated.title'),
            'body' => __('notifications.user_activated.body'),
            'icon' => 'user-check',
        ];
    }
}
