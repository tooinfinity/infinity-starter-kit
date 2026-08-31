<?php

declare(strict_types=1);

namespace App\Data\Users;

use Spatie\LaravelData\Data;

final class CreateUserData extends Data
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public bool $isActive = true,
        public array $roles = [],
    ) {}
}
