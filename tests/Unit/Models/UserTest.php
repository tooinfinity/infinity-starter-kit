<?php

declare(strict_types=1);

use App\Models\User;

test('to array', function (): void {
    $user = User::factory()->create()->refresh();

    expect(array_keys($user->toArray()))
        ->toBe([
            'id',
            'name',
            'email',
            /* @chisel-email-verification */
            'email_verified_at',
            /* @end-chisel-email-verification */
            /* @chisel-two-factor-authentication */
            'two_factor_confirmed_at',
            /* @end-chisel-two-factor-authentication */
            /* @chisel-user-management */
            'is_active',
            /* @end-chisel-user-management */
            /* @chisel-localization */
            'locale',
            /* @end-chisel-localization */
            'created_at',
            'updated_at',
        ]);
});
