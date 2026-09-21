<?php

declare(strict_types=1);

namespace App\Enums;

enum Permission: string
{
    case UsersView = 'users.view';
    case UsersCreate = 'users.create';
    case UsersUpdate = 'users.update';
    case UsersDelete = 'users.delete';
    /* @chisel-user-management */
    case UsersManageRoles = 'users.manage-roles';
    case UsersManagePassword = 'users.manage-password';
    /* @end-chisel-user-management */

    /* @chisel-settings */
    case SettingsManage = 'settings.manage';

    /* @end-chisel-settings */

    /* @chisel-audit-trails */
    case AuditView = 'audit.view';

    /* @end-chisel-audit-trails */

    /* @chisel-reporting */
    case ReportsView = 'reports.view';
    case ReportsExport = 'reports.export';

    /* @end-chisel-reporting */

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
