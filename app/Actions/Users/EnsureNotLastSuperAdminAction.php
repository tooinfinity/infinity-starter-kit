<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

final readonly class EnsureNotLastSuperAdminAction
{
    /**
     * Ensures the given user is not the last remaining Super Admin before a restricting mutation.
     * Must be called within an active transaction to preserve lock semantics.
     */
    public function handle(
        User $user,
        SuperAdminRestrictionOperation $operation,
        string $errorField = 'user',
    ): void {
        if (! trait_exists(HasRoles::class) || ! enum_exists(Role::class) || ! $user->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        $superAdminCount = User::query()
            ->role(Role::SuperAdmin->value)
            ->lockForUpdate()
            ->count();

        if ($superAdminCount <= 1) {
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
}
