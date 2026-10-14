<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

final readonly class EnsureNotLastSuperAdminAction
{
    /**
     * Ensures the given user is not the last remaining Super Admin before a restricting mutation.
     * Must be called within an active transaction to preserve lock semantics.
     *
     * @param  'delete'|'deactivate'|'remove_role'  $operation
     */
    public function handle(User $user, string $operation, string $errorField = 'user'): void
    {
        if (! trait_exists(HasRoles::class) || ! enum_exists(Role::class) || ! $user->hasRole(Role::SuperAdmin->value)) {
            return;
        }

        $superAdminCount = User::query()
            ->role(Role::SuperAdmin->value)
            ->lockForUpdate()
            ->count();

        if ($superAdminCount <= 1) {
            match ($operation) {
                'deactivate' => throw ValidationException::withMessages([
                    $errorField => __('Cannot deactivate the last Super Admin.'),
                ]),
                'remove_role' => throw ValidationException::withMessages([
                    $errorField => __('Cannot remove the Super Admin role from the last Super Admin.'),
                ]),
                default => throw ValidationException::withMessages([
                    $errorField => __('Cannot delete the last Super Admin.'),
                ]),
            };
        }
    }
}
