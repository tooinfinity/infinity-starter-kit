<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUser
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(User $user, array $attributes): void
    {
        /* @chisel-email-verification */
        $emailChanged = isset($attributes['email']) && $user->email !== $attributes['email'];
        /* @end-chisel-email-verification */

        $user->update([
            ...$attributes,
            /* @chisel-email-verification */
            ...($emailChanged ? ['email_verified_at' => null] : []),
            /* @end-chisel-email-verification */
        ]);

        /* @chisel-email-verification */
        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        /* @end-chisel-email-verification */
    }
}
