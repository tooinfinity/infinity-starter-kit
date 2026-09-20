<?php

declare(strict_types=1);

namespace App\Enums;

/* @chisel-audit-trails */

enum AuditEvent: string
{
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserActivated = 'user.activated';
    case UserDeactivated = 'user.deactivated';
    case UserDeleted = 'user.deleted';
    case UserPasswordChanged = 'user.password_changed';
    case SettingsUpdated = 'settings.updated';

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
            self::UserCreated => __('audit.events.user_created'),
            self::UserUpdated => __('audit.events.user_updated'),
            self::UserActivated => __('audit.events.user_activated'),
            self::UserDeactivated => __('audit.events.user_deactivated'),
            self::UserDeleted => __('audit.events.user_deleted'),
            self::UserPasswordChanged => __('audit.events.user_password_changed'),
            self::SettingsUpdated => __('audit.events.settings_updated'),
        };
    }
}

/* @end-chisel-audit-trails */
