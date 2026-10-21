<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\OtherDeviceLogout;
use Illuminate\Routing\Router;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Event;

it('registers AuthenticateSession on the web group', function (): void {
    $webMiddleware = resolve(Router::class)->getMiddlewareGroups()['web'];

    expect(
        in_array(AuthenticateSession::class, $webMiddleware, true)
        || in_array('auth.session', $webMiddleware, true)
    )->toBeTrue();
});

it('logs out a session whose stored password hash is stale', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['password_hash_web' => 'stale-hash'])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

it('invalidates other sessions after self-service password update while keeping the current session', function (): void {
    Event::fake([OtherDeviceLogout::class]);

    $user = User::factory()->create([
        'password' => 'old-password',
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('password.edit')
        ->put(route('password.update'), [
            'current_password' => 'old-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertRedirectToRoute('password.edit');
    $this->assertAuthenticatedAs($user);
    Event::assertDispatched(OtherDeviceLogout::class);

    $this->actingAs($user)
        ->withSession(['password_hash_web' => 'stale-hash'])
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});
