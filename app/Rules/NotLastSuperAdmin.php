<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\Role;
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\Traits\HasRoles;

final readonly class NotLastSuperAdmin implements ValidationRule
{
    public function __construct(
        private ?User $user,
        private SuperAdminRestrictionOperation $operation = SuperAdminRestrictionOperation::Deactivate,
    ) {}

    public static function check(
        User $user,
        SuperAdminRestrictionOperation $operation,
        string $errorField = 'user',
    ): void {
        if (! $user->is_active) {
            return;
        }

        if (! trait_exists(HasRoles::class) || ! enum_exists(Role::class) || ! $user->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        if (self::hasAtMostOneActiveSuperAdmin(lock: true)) {
            match ($operation) {
                SuperAdminRestrictionOperation::Deactivate => throw ValidationException::withMessages([
                    $errorField => __('Cannot deactivate the last Super Admin.'),
                ]),
                SuperAdminRestrictionOperation::RemoveRole => throw ValidationException::withMessages([
                    $errorField => __('Cannot remove the Super Admin role from the last Super Admin.'),
                ]),
                SuperAdminRestrictionOperation::Delete => throw ValidationException::withMessages([
                    $errorField => __('Cannot delete the last Super Admin.'),
                ]),
            };
        }
    }

    public static function validateRequest(
        ?User $user,
        SuperAdminRestrictionOperation $operation,
        Validator $validator,
        string $errorField = 'user',
    ): void {
        if (! $user instanceof User || ! $user->is_active) {
            return;
        }

        if (! trait_exists(HasRoles::class) || ! enum_exists(Role::class) || ! $user->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        if (self::hasAtMostOneActiveSuperAdmin()) {
            $message = match ($operation) {
                SuperAdminRestrictionOperation::Deactivate => __('Cannot deactivate the last Super Admin.'),
                SuperAdminRestrictionOperation::RemoveRole => __('Cannot remove the Super Admin role from the last Super Admin.'),
                SuperAdminRestrictionOperation::Delete => __('Cannot delete the last Super Admin.'),
            };

            $validator->errors()->add($errorField, $message);
        }
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $this->user instanceof User || ! $this->user->is_active) {
            return;
        }

        if (! trait_exists(HasRoles::class) || ! enum_exists(Role::class) || ! $this->user->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        if ($this->operation === SuperAdminRestrictionOperation::RemoveRole) {
            $roles = is_array($value) ? $value : [];

            if (in_array(Role::SuperAdmin->value, $roles, true)) {
                return;
            }
        }

        if (self::hasAtMostOneActiveSuperAdmin()) {
            match ($this->operation) {
                SuperAdminRestrictionOperation::Deactivate => $fail(__('Cannot deactivate the last Super Admin.')),
                SuperAdminRestrictionOperation::RemoveRole => $fail(__('Cannot remove the Super Admin role from the last Super Admin.')),
                SuperAdminRestrictionOperation::Delete => $fail(__('Cannot delete the last Super Admin.')),
            };
        }
    }

    private static function hasAtMostOneActiveSuperAdmin(bool $lock = false): bool
    {
        if ($lock) {
            RoleModel::query()
                ->where('name', Role::SuperAdmin->value)
                ->lockForUpdate()
                ->get(['id']);
        }

        $query = User::query()
            ->role(Role::SuperAdmin->value)
            ->where('is_active', true);

        if ($lock) {
            // Use a current read even when MySQL has already established a transaction snapshot.
            $superAdmins = $query->lockForUpdate()->get(['users.id']);

            return count($superAdmins) <= 1;
        }

        return $query->count() <= 1;
    }
}
