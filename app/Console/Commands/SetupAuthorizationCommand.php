<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

#[Signature('authorization:setup')]
#[Description('Create all permissions and roles defined in the application enums')]
final class SetupAuthorizationCommand extends Command
{
    public function handle(): int
    {
        $this->components->info('Setting up authorization...');

        $permissions = Permission::values();

        foreach ($permissions as $permissionName) {
            PermissionModel::findOrCreate($permissionName);
        }

        $this->components->twoColumnDetail('Permissions synchronized', (string) count($permissions));

        $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
        $superAdminRole->syncPermissions($permissions);

        $this->components->twoColumnDetail('Super Admin role', 'created with all permissions');

        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->components->info('Authorization setup complete.');

        return self::SUCCESS;
    }
}
