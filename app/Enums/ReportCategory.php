<?php

declare(strict_types=1);

namespace App\Enums;

/* @chisel-reporting */

enum ReportCategory: string
{
    case Users = 'users';
    case Security = 'security';

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
            self::Users => __('reports.categories.users'),
            self::Security => __('reports.categories.security'),
        };
    }
}

/* @end-chisel-reporting */
