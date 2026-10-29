<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Enums\Permission;
use App\Models\User;
use Spatie\Permission\Models\Permission as PermissionModel;

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

test('Inertia shared props contain translated strings for Arabic locale', function (): void {
    $user = User::factory()->withLocale(Locale::Arabic)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'ar')
            ->where('direction', 'rtl')
            ->has('lang')
            ->where('lang.Dashboard', 'لوحة القيادة')
            ->where('lang.Notifications', 'الإشعارات')
            ->where('lang.Two-Factor Authentication', 'المصادقة الثنائية')
            ->where('lang.Appearance settings', 'إعدادات المظهر')
        );
});

test('Inertia shared props contain translated strings for French locale', function (): void {
    $user = User::factory()->withLocale(Locale::French)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'fr')
            ->where('direction', 'ltr')
            ->has('lang')
            ->where('lang.Dashboard', 'Tableau de bord')
            ->where('lang.Notifications', 'Notifications')
            ->where('lang.Two-Factor Authentication', 'Authentification à deux facteurs')
            ->where('lang.Appearance settings', "Paramètres d'apparence")
        );
});

test('unauthenticated pages share correct locale and translations', function (string $url): void {
    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'en')
            ->where('direction', 'ltr')
            ->has('lang')
            ->where('lang.Log in', 'Log in')
        );
})->with([
    fn (): string => route('home'),
    fn (): string => route('login'),
    fn (): string => route('register'),
    fn (): string => route('password.request'),
]);

test('settings pages share correct locale and direction for Arabic user', function (string $routeName, bool $requiresAdmin = false, bool $requiresConfirmedPassword = false): void {
    $user = User::factory()->withLocale(Locale::Arabic)->create();

    if ($requiresAdmin) {
        $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
        $user->givePermissionTo($permission);
    }

    $request = $this->actingAs($user);

    if ($requiresConfirmedPassword) {
        $request->withSession(['auth.password_confirmed_at' => time()]);
    }

    $request->get(route($routeName))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('locale', 'ar')
            ->where('direction', 'rtl')
            ->has('lang')
        );
})->with([
    ['user-profile.edit', false, false],
    ['password.edit', false, false],
    ['appearance.edit', false, false],
    ['settings.edit', true, false],
    ['notification-preferences.edit', false, false],
    ['notifications.index', false, false],
    ['two-factor.show', false, true],
]);
