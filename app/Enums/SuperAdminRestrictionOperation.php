<?php

declare(strict_types=1);

namespace App\Enums;

enum SuperAdminRestrictionOperation: string
{
    case Delete = 'delete';
    case Deactivate = 'deactivate';
    case RemoveRole = 'remove_role';
}
