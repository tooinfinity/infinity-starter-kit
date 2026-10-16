<?php

declare(strict_types=1);

namespace App\Data\Users;

use App\Models\User;
use Spatie\LaravelData\Data;
use Spatie\Permission\Traits\HasRoles;

final class UserData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $email,
        public bool $is_active,
        /* @chisel-email-verification */
        public ?string $email_verified_at,
        /* @end-chisel-email-verification */
        public array $roles,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function fromModel(User $user): self
    {
        /** @var list<string> $roles */
        $roles = trait_exists(HasRoles::class) ? array_values($user->getRoleNames()->all()) : [];

        return new self(
            id: (string) $user->id,
            name: $user->name,
            email: $user->email,
            is_active: (bool) $user->is_active,
            /* @chisel-email-verification */
            email_verified_at: $user->email_verified_at?->toISOString(),
            /* @end-chisel-email-verification */
            roles: $roles,
            created_at: (string) $user->created_at->toISOString(),
            updated_at: (string) $user->updated_at->toISOString(),
        );
    }
}
