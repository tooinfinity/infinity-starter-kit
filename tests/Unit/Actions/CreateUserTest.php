<?php

declare(strict_types=1);

use App\Actions\CreateUser;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('creates a user and dispatches registered event', function (): void {
    Event::fake([Registered::class]);

    $action = resolve(CreateUser::class);

    $user = $action->handle([
        'name' => 'Test User',
        'email' => 'example@email.com',
    ], 'password');

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->name)->toBe('Test User')
        ->and($user->email)->toBe('example@email.com')
        ->and(Hash::check('password', $user->password))->toBeTrue();

    Event::assertDispatched(Registered::class);
});
