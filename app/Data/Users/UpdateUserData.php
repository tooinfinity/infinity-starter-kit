<?php

declare(strict_types=1);

namespace App\Data\Users;

use Spatie\LaravelData\Data;

final class UpdateUserData extends Data
{
    /**
     * @param  list<string>|null  $roles
     */
    public function __construct(
        public string $name,
        public string $email,
        public ?bool $isActive = null,
        public ?array $roles = null,
    ) {}
}
