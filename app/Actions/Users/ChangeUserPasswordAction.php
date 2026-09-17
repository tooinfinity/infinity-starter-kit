<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use SensitiveParameter;

final readonly class ChangeUserPasswordAction
{
    public function handle(User $user, #[SensitiveParameter] string $password): void
    {
        $user->forceFill([
            'password' => $password,
        ])->save();
    }
}
