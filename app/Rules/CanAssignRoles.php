<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Spatie\Permission\Models\Role as RoleModel;

final readonly class CanAssignRoles implements ValidationRule
{
    public function __construct(
        private ?User $actor,
        private ?User $target = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->actor instanceof User) {
            return;
        }

        $canManageRoles = ! enum_exists(Permission::class) || $this->actor->can(Permission::UsersManageRoles->value);

        if (! $canManageRoles) {
            return;
        }

        if ($this->actor->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        $targetRoles = $this->target instanceof User
            ? array_values(array_filter($this->target->getRoleNames()->all(), is_string(...)))
            : [];
        $submittedRoles = is_array($value)
            ? array_values(array_unique(array_filter($value, is_string(...))))
            : [];
        $rolesToAdd = array_values(array_diff($submittedRoles, $targetRoles));

        $targetHasSuperAdmin = in_array(Role::SuperAdmin->value, $targetRoles, true);
        $submittedHasSuperAdmin = in_array(Role::SuperAdmin->value, $submittedRoles, true);

        if ($submittedHasSuperAdmin && ! $targetHasSuperAdmin) {
            $fail('Only Super Admins can assign the Super Admin role.');

            return;
        }

        if (! $submittedHasSuperAdmin && $targetHasSuperAdmin) {
            $fail('Only Super Admins can remove the Super Admin role.');

            return;
        }

        $rolesToCheck = array_values(array_diff($rolesToAdd, [Role::SuperAdmin->value]));

        if ($rolesToCheck === []) {
            return;
        }

        $actorPermissions = $this->actor->getAllPermissions()->pluck('name');

        $roles = RoleModel::query()
            ->whereIn('name', $rolesToCheck)
            ->with('permissions')
            ->get();

        foreach ($roles as $role) {
            $missing = $role->permissions->pluck('name')->diff($actorPermissions);

            if ($missing->isNotEmpty()) {
                $fail('You cannot assign a role with permissions you do not have.');

                return;
            }
        }
    }
}
