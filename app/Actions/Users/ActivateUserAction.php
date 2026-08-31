<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;

final readonly class ActivateUserAction
{
    public function handle(User $user): void
    {
        $user->forceFill(['is_active' => true])->save();
    }
}
