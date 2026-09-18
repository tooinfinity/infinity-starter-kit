<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

/* @chisel-notifications */

test('PasswordChanged notification implements ShouldQueue', function (): void {
    $notification = new PasswordChanged;

    expect($notification)->toBeInstanceOf(ShouldQueue::class);
});

test('PasswordChanged notification uses database channel', function (): void {
    $user = User::factory()->make();
    $notification = new PasswordChanged;

    expect($notification->via($user))->toBe(['database']);
});

test('PasswordChanged notification has correct database payload structure', function (): void {
    $user = User::factory()->make();
    $notification = new PasswordChanged;

    $data = $notification->toDatabase($user);

    expect($data)->toHaveKeys(['type', 'title', 'body', 'icon'])
        ->and($data['type'])->toBe(NotificationType::Security->value)
        ->and($data['title'])->toBe('Password Changed')
        ->and($data['body'])->toContain('password was recently changed')
        ->and($data['icon'])->toBe('shield');
});

test('PasswordChanged notification translates title and body according to app locale', function (): void {
    $user = User::factory()->make();
    $notification = new PasswordChanged;

    app()->setLocale('fr');
    $dataFr = $notification->toDatabase($user);
    expect($dataFr['title'])->toBe('Mot de passe modifié')
        ->and($dataFr['body'])->toContain('Votre mot de passe a été modifié');

    app()->setLocale('ar');
    $dataAr = $notification->toDatabase($user);
    expect($dataAr['title'])->toBe('تم تغيير كلمة المرور');

    app()->setLocale('en');
});

/* @end-chisel-notifications */
