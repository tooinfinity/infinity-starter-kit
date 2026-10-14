<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use Spatie\Permission\Traits\HasRoles;

final readonly class SnapshotUserAuditDataAction
{
    /**
     * @param  list<string>|null  $explicitRoles
     * @return array{name: string, email: string, is_active: bool, roles: list<string>}
     */
    public function handle(User $user, ?array $explicitRoles = null): array
    {
        /** @var list<string> $roles */
        $roles = $explicitRoles ?? (trait_exists(HasRoles::class) ? array_values($user->getRoleNames()->all()) : []);

        return [
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'roles' => $roles,
        ];
    }
}
