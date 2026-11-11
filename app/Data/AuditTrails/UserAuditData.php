<?php

declare(strict_types=1);

namespace App\Data\AuditTrails;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;
use Spatie\Permission\Traits\HasRoles;

final class UserAuditData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $name,
        public string $email,
        public Optional|bool $is_active,
        public array $roles,
    ) {}

    /**
     * @param  list<string>|null  $explicitRoles
     */
    public static function fromModel(User $user, ?array $explicitRoles = null): self
    {
        /** @var list<string> $roles */
        $roles = $explicitRoles ?? (in_array(HasRoles::class, class_uses_recursive($user), true) ? array_values($user->getRoleNames()->all()) : []);

        $isActive = array_key_exists('is_active', $user->getAttributes())
            ? (bool) $user->is_active
            : new Optional();

        return new self(
            name: $user->name,
            email: $user->email,
            is_active: $isActive,
            roles: $roles,
        );
    }

    /**
     * @return array{name: string, email: string, is_active?: bool, roles: list<string>}
     */
    public function toArray(): array
    {
        /** @var array{name: string, email: string, is_active?: bool, roles: list<string>} */
        return parent::toArray();
    }
}
