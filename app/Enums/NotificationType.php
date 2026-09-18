<?php

declare(strict_types=1);

namespace App\Enums;

/* @chisel-notifications */

enum NotificationType: string
{
    case Security = 'security';
    case UserManagement = 'user_management';
    case System = 'system';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Security => __('notifications.types.security'),
            self::UserManagement => __('notifications.types.user_management'),
            self::System => __('notifications.types.system'),
        };
    }

    public function isMandatory(): bool
    {
        return match ($this) {
            self::Security => true,
            self::UserManagement, self::System => false,
        };
    }
}

/* @end-chisel-notifications */
