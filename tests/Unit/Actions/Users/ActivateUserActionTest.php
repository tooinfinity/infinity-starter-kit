<?php

declare(strict_types=1);

use App\Actions\Users\ActivateUserAction;
use App\Models\User;

test('ActivateUserAction activates inactive user', function (): void {
    $user = User::factory()->create(['is_active' => false]);

    $action = resolve(ActivateUserAction::class);
    $action->handle($user);

    expect($user->fresh()->is_active)->toBeTrue();
});
