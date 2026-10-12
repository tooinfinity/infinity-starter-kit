<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

final class AuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        if (! enum_exists(Permission::class) || ! enum_exists(Role::class)) {
            return;
        }

        $permissions = Permission::values();

        foreach ($permissions as $permissionName) {
            PermissionModel::findOrCreate($permissionName);
        }

        $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
        $superAdminRole->syncPermissions($permissions);

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
