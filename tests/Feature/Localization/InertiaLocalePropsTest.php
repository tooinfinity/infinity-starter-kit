<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;

test('Inertia shared props contain locale', function (): void {
    $user = User::factory()->withLocale(Locale::French)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'fr')
        );
});

test('Inertia shared props contain direction', function (): void {
    $user = User::factory()->withLocale(Locale::Arabic)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('direction', 'rtl')
        );
});

test('Inertia shared props contain supportedLocales', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('supportedLocales', 3)
            ->where('supportedLocales.0.code', 'en')
            ->where('supportedLocales.0.name', 'English')
            ->where('supportedLocales.0.nativeName', 'English')
            ->where('supportedLocales.0.direction', 'ltr')
            ->where('supportedLocales.1.code', 'fr')
            ->where('supportedLocales.1.nativeName', 'Français')
            ->where('supportedLocales.2.code', 'ar')
            ->where('supportedLocales.2.nativeName', 'العربية')
            ->where('supportedLocales.2.direction', 'rtl')
        );
});

test('default locale is English when user has no preference', function (): void {
    $user = User::factory()->create(['locale' => null]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'en')
            ->where('direction', 'ltr')
        );
});

test('LTR direction for French locale', function (): void {
    $user = User::factory()->withLocale(Locale::French)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'fr')
            ->where('direction', 'ltr')
        );
});

test('advertises lang as a once prop keyed by locale', function (): void {
    $response = $this->get(route('login'))->assertOk();

    $page = $response->viewData('page');
    expect($page['onceProps'] ?? [])->toHaveKey('lang.en');
});

test('second Inertia visit in the same locale does not include lang', function (): void {
    $initial = $this->get(route('login'))->assertOk();

    $initial->assertInertia(fn (Assert $page): Assert => $page
        ->has('lang')
    );

    $page = $initial->viewData('page');
    $version = $page['version'] ?? (string) Inertia::getVersion();

    $second = $this->withHeaders([
        'X-Inertia' => 'true',
        'X-Inertia-Version' => $version,
        'X-Inertia-Except-Once-Props' => 'lang.en',
        'X-Requested-With' => 'XMLHttpRequest',
    ])->get(route('login'))->assertOk();

    expect($second->json('props'))->not->toHaveKey('lang')
        ->and($second->json('onceProps'))->toHaveKey('lang.en');
});

test('switching locale returns the new dictionary', function (): void {
    $user = User::factory()->withLocale(Locale::French)->create();

    $initial = $this->get(route('login'))->assertOk();
    $version = $initial->viewData('page')['version'] ?? (string) Inertia::getVersion();

    $response = $this->actingAs($user)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Inertia-Except-Once-Props' => 'lang.en',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('dashboard'))
        ->assertOk();

    expect($response->json('props'))->toHaveKey('lang')
        ->and($response->json('onceProps'))->toHaveKey('lang.fr')
        ->and($response->json('props.lang.common.actions.save'))->toBe('Enregistrer');
});

test('only one file read per locale per process', function (): void {
    HandleInertiaRequests::flushTranslationsCache();

    File::partialMock()
        ->shouldReceive('get')
        ->with(lang_path('en.json'))
        ->once()
        ->passthru();

    $this->get(route('login'))->assertOk();

    $middleware = new HandleInertiaRequests();
    $request = Request::create(route('login'), 'GET');
    $shared = $middleware->share($request);

    expect($shared['lang']())->toBeArray();
});
