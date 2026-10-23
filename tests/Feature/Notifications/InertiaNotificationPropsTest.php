<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

test('guest has unreadCount 0 and empty recent in shared props', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('notifications.unreadCount', 0)
            ->where('notifications.recent', [])
        );
});

test('does not load recent notifications on a normal page visit', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);
    $user->notify(new PasswordChanged);

    DB::enableQueryLog();
    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $recentQueries = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains(mb_strtolower($q['query']), 'notifications') && str_contains(mb_strtolower($q['query']), 'limit 5'));

    expect($recentQueries)->toBeEmpty();
});

test('does not advertise recent notifications as a deferred prop', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('dashboard'))->assertOk();

    $page = $response->viewData('page');
    expect($page['deferredProps'] ?? [])->toBe([]);

    $inertiaResponse = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) Inertia::getVersion(),
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('dashboard'));

    expect($inertiaResponse->json('deferredProps') ?? [])->toBe([]);
});

test('authenticated user receives unreadCount and loads recent notifications on explicit partial reload', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);
    $user->notify(new PasswordChanged);

    DB::enableQueryLog();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('notifications.unreadCount', 2)
            ->missing('notifications.recent')
            ->reloadOnly('notifications.recent', function (Assert $page): void {
                $recentQueries = collect(DB::getQueryLog())
                    ->filter(fn (array $q): bool => str_contains(mb_strtolower($q['query']), 'notifications') && str_contains(mb_strtolower($q['query']), 'limit 5'));

                expect($recentQueries)->toHaveCount(1);

                $page
                    ->has('notifications.recent', 2)
                    ->where('notifications.recent.0.title', 'Password Changed');
            })
        );
});

test('unreadCount updates when notification is marked as read', function (): void {
    $user = User::factory()->create();
    $user->notify(new PasswordChanged);

    $notification = $user->unreadNotifications()->first();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('notifications.unreadCount', 1)
        );

    $this->actingAs($user)
        ->patch(route('notifications.mark-read', ['notification' => $notification->id]));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('notifications.unreadCount', 0)
        );
});
