<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Data\Users\UpdateUserData;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateUserAction
{
    public function handle(User $user, UpdateUserData $data, ?User $currentUser = null): User
    {
        if ($currentUser instanceof User && $user->id === $currentUser->id && ! $data->isActive) {
            throw ValidationException::withMessages([
                'is_active' => __('You cannot deactivate your own account.'),
            ]);
        }

        $isSuperAdmin = $user->hasRole(Role::SuperAdmin->value);

        if ($isSuperAdmin) {
            $superAdminCount = User::query()->role(Role::SuperAdmin->value)->count();

            if ($superAdminCount <= 1) {
                if (! $data->isActive) {
                    throw ValidationException::withMessages([
                        'is_active' => __('Cannot deactivate the last Super Admin.'),
                    ]);
                }

                if (! in_array(Role::SuperAdmin->value, $data->roles, true)) {
                    throw ValidationException::withMessages([
                        'roles' => __('Cannot remove the Super Admin role from the last Super Admin.'),
                    ]);
                }
            }
        }

        return DB::transaction(function () use ($user, $data): User {
            $emailChanged = $user->email !== $data->email;

            $user->update([
                'name' => $data->name,
                'email' => $data->email,
                'is_active' => $data->isActive,
                ...($emailChanged ? ['email_verified_at' => null] : []),
            ]);

            if ($emailChanged) {
                $user->sendEmailVerificationNotification();
            }

            $user->syncRoles($data->roles);

            return $user;
        });
    }
}
