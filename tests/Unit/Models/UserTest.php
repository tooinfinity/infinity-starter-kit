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
            'email_verified_at',
            'two_factor_confirmed_at',
            /* @chisel-user-management */
            'is_active',
            /* @end-chisel-user-management */
            'created_at',
            'updated_at',
        ]);
});
