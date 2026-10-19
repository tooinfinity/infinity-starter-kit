<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role as RoleModel;

final readonly class EnsureActorMayAssignRolesAction
{
    /**
     * @param  list<string>|array<mixed>  $submittedRoles
     */
    public function handle(
        Validator $validator,
        ?User $actor,
        array $submittedRoles,
        ?User $target = null,
    ): void {
        if (! $actor instanceof User) {
            return;
        }

        $canManageRoles = ! enum_exists(Permission::class) || $actor->can(Permission::UsersManageRoles->value);

        if (! $canManageRoles) {
            return;
        }

        if ($actor->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        $targetRoles = $target instanceof User
            ? array_values(array_filter($target->getRoleNames()->all(), is_string(...)))
            : [];
        $submittedRoles = array_values(array_unique(array_filter($submittedRoles, is_string(...))));
        $rolesToAdd = array_values(array_diff($submittedRoles, $targetRoles));

        $targetHasSuperAdmin = in_array(Role::SuperAdmin->value, $targetRoles, true);
        $submittedHasSuperAdmin = in_array(Role::SuperAdmin->value, $submittedRoles, true);

        if ($submittedHasSuperAdmin && ! $targetHasSuperAdmin) {
            $validator->errors()->add('roles', __('Only Super Admins can assign the Super Admin role.'));
        } elseif (! $submittedHasSuperAdmin && $targetHasSuperAdmin) {
            $validator->errors()->add('roles', __('Only Super Admins can remove the Super Admin role.'));
        }

        if ($validator->errors()->has('roles')) {
            return;
        }

        $rolesToCheck = array_values(array_diff($rolesToAdd, [Role::SuperAdmin->value]));

        if ($rolesToCheck === []) {
            return;
        }

        $actorPermissions = $actor->getAllPermissions()->pluck('name');

        $roles = RoleModel::query()
            ->whereIn('name', $rolesToCheck)
            ->with('permissions')
            ->get();

        foreach ($roles as $role) {
            $missing = $role->permissions->pluck('name')->diff($actorPermissions);

            if ($missing->isNotEmpty()) {
                $validator->errors()->add('roles', __('You cannot assign a role with permissions you do not have.'));
                break;
            }
        }
    }

    /**
     * @param  list<string>|array<mixed>  $submittedRoles
     */
    public function ensure(
        ?User $actor,
        array $submittedRoles,
        ?User $target = null,
    ): void {
        $validator = ValidatorFacade::make([], []);
        $this->handle($validator, $actor, $submittedRoles, $target);

        throw_if($validator->errors()->isNotEmpty(), ValidationException::class, $validator);
    }
}
