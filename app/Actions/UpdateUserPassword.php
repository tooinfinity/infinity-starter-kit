<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
/* @chisel-notifications */
use App\Notifications\PasswordChanged;
/* @end-chisel-notifications */
use SensitiveParameter;

final readonly class UpdateUserPassword
{
    public function handle(User $user, #[SensitiveParameter] string $password): void
    {
        $user->update([
            'password' => $password,
        ]);

        /* @chisel-notifications */
        $user->notify(new PasswordChanged);
        /* @end-chisel-notifications */
    }
}
