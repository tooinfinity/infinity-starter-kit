<?php

declare(strict_types=1);

use App\Actions\UpdateUserPassword;
use App\Models\User;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('may update a user password', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    $action = resolve(UpdateUserPassword::class);

    $action->handle($user, 'new-password');

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue()
        ->and(Hash::check('old-password', $user->password))->toBeFalse();
});

it('logs out other devices when authenticated user updates password', function (): void {
    Event::fake([OtherDeviceLogout::class]);

    $user = User::factory()->create([
        'password' => Hash::make('old-password'),
    ]);

    Auth::login($user);

    $action = resolve(UpdateUserPassword::class);
    $action->handle($user, 'new-password');

    Event::assertDispatched(OtherDeviceLogout::class);
});
