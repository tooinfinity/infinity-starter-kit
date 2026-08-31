<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final readonly class DeleteUserAction
{
    public function handle(User $user, ?User $currentUser = null): void
    {
        if ($currentUser instanceof User && $user->id === $currentUser->id) {
            throw ValidationException::withMessages([
                'user' => __('You cannot delete your own account.'),
            ]);
        }

        if ($user->hasRole(Role::SuperAdmin->value)) {
            $superAdminCount = User::query()->role(Role::SuperAdmin->value)->count();

            if ($superAdminCount <= 1) {
                throw ValidationException::withMessages([
                    'user' => __('Cannot delete the last Super Admin.'),
                ]);
            }
        }

        $user->delete();
    }
}
