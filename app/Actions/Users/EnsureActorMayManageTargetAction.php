<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Permission\Traits\HasRoles;

final readonly class EnsureActorMayManageTargetAction
{
    public function handle(User $actor, User $target): bool
    {
        if (! trait_exists(HasRoles::class) || ! enum_exists(Role::class)) {
            return true;
        }

        return ! $target->hasRole(Role::SuperAdmin->value)
            || $actor->hasRole(Role::SuperAdmin->value);
    }

    public function ensure(User $actor, User $target): void
    {
        if (! $this->handle($actor, $target)) {
            throw new AuthorizationException(__('You do not have permission to manage this Super Admin.'));
        }
    }
}
