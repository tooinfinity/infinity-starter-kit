<?php

declare(strict_types=1);

use App\Actions\UpdateUserLocale;
use App\Enums\Locale;
use App\Models\User;

test('UpdateUserLocale updates the users locale', function (): void {
    $user = User::factory()->create(['locale' => null]);

    $action = resolve(UpdateUserLocale::class);
    $action->handle($user, Locale::French);

    expect($user->fresh()->locale)->toBe(Locale::French);
});
