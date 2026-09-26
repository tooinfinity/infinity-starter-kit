<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\AuditTrail;

enum ReportType: string
{
    case UserActivity = 'user_activity';
    case AuditActivity = 'audit_activity';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<self>
     */
    public static function availableCases(): array
    {
        return array_filter(
            self::cases(),
            fn (self $case): bool => match ($case) {
                self::UserActivity => true,
                self::AuditActivity => class_exists(AuditTrail::class),
            },
        );
    }

    public function label(): string
    {
        return match ($this) {
            self::UserActivity => __('reports.types.user_activity.title'),
            self::AuditActivity => __('reports.types.audit_activity.title'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::UserActivity => __('reports.types.user_activity.description'),
            self::AuditActivity => __('reports.types.audit_activity.description'),
        };
    }

    public function category(): ReportCategory
    {
        return match ($this) {
            self::UserActivity => ReportCategory::Users,
            self::AuditActivity => ReportCategory::Security,
        };
    }

    public function route(): string
    {
        return match ($this) {
            self::UserActivity => route('reports.users'),
            self::AuditActivity => route('reports.audit'),
        };
    }

    public function permission(): string
    {
        return enum_exists(Permission::class) ? Permission::ReportsView->value : 'reports.view';
    }
}
