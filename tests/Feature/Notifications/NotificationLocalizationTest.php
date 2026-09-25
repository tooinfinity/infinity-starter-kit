<?php

declare(strict_types=1);

use App\Enums\Locale;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Notifications\UserActivated;
use App\Notifications\UserDeactivated;

test('user preferredLocale returns their locale value', function (): void {
    $user = User::factory()->create(['locale' => Locale::French]);

    expect($user->preferredLocale())->toBe('fr');
});

test('user preferredLocale returns null when not set', function (): void {
    $user = User::factory()->create(['locale' => null]);

    expect($user->preferredLocale())->toBeNull();
});

test('notifications sent in French locale are translated in French', function (): void {
    app()->setLocale('fr');

    $user = User::factory()->create();
    $user->notify(new PasswordChanged);
    $user->notify(new UserActivated);
    $user->notify(new UserDeactivated);

    $notifications = $user->notifications()->oldest()->get();

    expect($notifications[0]->data['title'])->toBe('Mot de passe modifié')
        ->and($notifications[1]->data['title'])->toBe('Compte activé')
        ->and($notifications[2]->data['title'])->toBe('Compte désactivé');

    app()->setLocale('en');
});

test('notifications sent in Arabic locale are translated in Arabic', function (): void {
    app()->setLocale('ar');

    $user = User::factory()->create();
    $user->notify(new PasswordChanged);
    $user->notify(new UserActivated);
    $user->notify(new UserDeactivated);

    $notifications = $user->notifications()->oldest()->get();

    expect($notifications[0]->data['title'])->toBe('تم تغيير كلمة المرور')
        ->and($notifications[1]->data['title'])->toBe('تم تفعيل الحساب')
        ->and($notifications[2]->data['title'])->toBe('تم تعطيل الحساب');

    app()->setLocale('en');
});
