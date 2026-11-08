<?php

declare(strict_types=1);

namespace App\Enums;

enum AuditEvent: string
{
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserActivated = 'user.activated';
    case UserDeactivated = 'user.deactivated';
    case UserDeleted = 'user.deleted';
    case UserPasswordChanged = 'user.password_changed';
    case UserRoleChanged = 'user.role_changed';
    case SettingsUpdated = 'settings.updated';
    case AuthLogin = 'auth.login';
    case AuthLoginFailed = 'auth.login_failed';
    case AuthLockout = 'auth.lockout';
    case AuthLogout = 'auth.logout';
    case AuthPasswordReset = 'auth.password_reset';
    case AuthTwoFactorEnabled = 'auth.two_factor_enabled';
    case AuthTwoFactorDisabled = 'auth.two_factor_disabled';
    case AuthRecoveryCodesUsed = 'auth.recovery_codes_used';

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
            self::UserRoleChanged => __('audit.events.user_role_changed'),
            self::SettingsUpdated => __('audit.events.settings_updated'),
            self::AuthLogin => __('audit.events.auth_login'),
            self::AuthLoginFailed => __('audit.events.auth_login_failed'),
            self::AuthLockout => __('audit.events.auth_lockout'),
            self::AuthLogout => __('audit.events.auth_logout'),
            self::AuthPasswordReset => __('audit.events.auth_password_reset'),
            self::AuthTwoFactorEnabled => __('audit.events.auth_two_factor_enabled'),
            self::AuthTwoFactorDisabled => __('audit.events.auth_two_factor_disabled'),
            self::AuthRecoveryCodesUsed => __('audit.events.auth_recovery_codes_used'),
        };
    }
}
