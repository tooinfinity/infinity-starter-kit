<?php

declare(strict_types=1);

namespace App\Queries\Users;

use App\Enums\Role as RoleEnum;
use Spatie\Permission\Models\Role as RoleModel;

final readonly class UserRolesQuery
{
    /**
     * @return list<string>
     */
    public function handle(): array
    {
        if (class_exists(RoleModel::class)) {
            /** @var list<string> $roles */
            $roles = RoleModel::query()->pluck('name')->all();

            if (! empty($roles)) {
                return $roles;
            }
        }

        return enum_exists(RoleEnum::class) ? RoleEnum::values() : [];
    }
}
