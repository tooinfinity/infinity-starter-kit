<?php

declare(strict_types=1);

use App\Actions\Users\ChangeUserPasswordAction;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('ChangeUserPasswordAction changes user password', function (): void {
    $user = User::factory()->create();

    $action = resolve(ChangeUserPasswordAction::class);
    $action->handle($user, 'new-secret-password123');

    expect(Hash::check('new-secret-password123', $user->fresh()->password))->toBeTrue();
});
