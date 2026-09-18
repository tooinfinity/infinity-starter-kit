<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
/* @chisel-notifications */
use App\Notifications\UserActivated;

/* @end-chisel-notifications */

final readonly class ActivateUserAction
{
    public function handle(User $user): void
    {
        $user->forceFill(['is_active' => true])->save();

        /* @chisel-notifications */
        $user->notify(new UserActivated);
        /* @end-chisel-notifications */
    }
}
