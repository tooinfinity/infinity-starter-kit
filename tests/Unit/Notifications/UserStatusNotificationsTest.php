<?php

declare(strict_types=1);

use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\UserActivated;
use App\Notifications\UserDeactivated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

test('UserActivated notification implements ShouldQueueAfterCommit and ShouldQueue', function (): void {
    $notification = new UserActivated;

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification)->toBeInstanceOf(ShouldQueueAfterCommit::class);
});

test('UserActivated notification uses database channel', function (): void {
    $user = User::factory()->make();
    $notification = new UserActivated;

    expect($notification->via($user))->toBe(['database']);
});

test('UserActivated notification has correct database payload structure', function (): void {
    $user = User::factory()->make();
    $notification = new UserActivated;

    $data = $notification->toDatabase($user);

    expect($data)->toHaveKeys(['type', 'title', 'body', 'icon'])
        ->and($data['type'])->toBe(NotificationType::UserManagement->value)
        ->and($data['title'])->toBe('Account Activated')
        ->and($data['body'])->toContain('Your account has been activated')
        ->and($data['icon'])->toBe('user-check');
});

/* @chisel-localization */
test('UserActivated notification translates title and body according to app locale', function (): void {
    $user = User::factory()->make();
    $notification = new UserActivated;

    app()->setLocale('fr');
    $dataFr = $notification->toDatabase($user);
    expect($dataFr['title'])->toBe('Compte activé')
        ->and($dataFr['body'])->toContain('Votre compte a été activé');

    app()->setLocale('ar');
    $dataAr = $notification->toDatabase($user);
    expect($dataAr['title'])->toBe('تم تفعيل الحساب');

    app()->setLocale('en');
});
/* @end-chisel-localization */

test('UserDeactivated notification implements ShouldQueueAfterCommit and ShouldQueue', function (): void {
    $notification = new UserDeactivated;

    expect($notification)->toBeInstanceOf(ShouldQueue::class)
        ->and($notification)->toBeInstanceOf(ShouldQueueAfterCommit::class);
});

test('UserDeactivated notification uses database channel', function (): void {
    $user = User::factory()->make();
    $notification = new UserDeactivated;

    expect($notification->via($user))->toBe(['database']);
});

test('UserDeactivated notification has correct database payload structure', function (): void {
    $user = User::factory()->make();
    $notification = new UserDeactivated;

    $data = $notification->toDatabase($user);

    expect($data)->toHaveKeys(['type', 'title', 'body', 'icon'])
        ->and($data['type'])->toBe(NotificationType::UserManagement->value)
        ->and($data['title'])->toBe('Account Deactivated')
        ->and($data['body'])->toContain('Your account has been deactivated')
        ->and($data['icon'])->toBe('user-x');
});

/* @chisel-localization */
test('UserDeactivated notification translates title and body according to app locale', function (): void {
    $user = User::factory()->make();
    $notification = new UserDeactivated;

    app()->setLocale('fr');
    $dataFr = $notification->toDatabase($user);
    expect($dataFr['title'])->toBe('Compte désactivé')
        ->and($dataFr['body'])->toContain('Votre compte a été désactivé');

    app()->setLocale('ar');
    $dataAr = $notification->toDatabase($user);
    expect($dataAr['title'])->toBe('تم تعطيل الحساب');

    app()->setLocale('en');
});
/* @end-chisel-localization */
