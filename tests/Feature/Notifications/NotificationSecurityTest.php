<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Str;

/* @chisel-notifications */

test('user cannot mark another users notification as read via IDOR', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $userB->notify(new PasswordChanged);

    $notificationB = $userB->notifications()->first();

    $this->actingAs($userA)
        ->patch(route('notifications.mark-read', ['notification' => $notificationB->id]))
        ->assertForbidden();

    expect($notificationB->fresh()->read_at)->toBeNull();
});

test('user cannot delete another users notification via IDOR', function (): void {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $userB->notify(new PasswordChanged);

    $notificationB = $userB->notifications()->first();

    $this->actingAs($userA)
        ->delete(route('notifications.destroy', ['notification' => $notificationB->id]))
        ->assertForbidden();

    expect($notificationB->fresh())->not->toBeNull();
});

test('non-existent notification id returns 403 on mark read', function (): void {
    $user = User::factory()->create();
    $randomUuid = (string) Str::uuid();

    $this->actingAs($user)
        ->patch(route('notifications.mark-read', ['notification' => $randomUuid]))
        ->assertForbidden();
});

test('non-existent notification id returns 403 on delete', function (): void {
    $user = User::factory()->create();
    $randomUuid = (string) Str::uuid();

    $this->actingAs($user)
        ->delete(route('notifications.destroy', ['notification' => $randomUuid]))
        ->assertForbidden();
});

test('invalid uuid format returns 403 on mark read', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('notifications.mark-read', ['notification' => 'not-a-uuid']))
        ->assertForbidden();
});

/* @end-chisel-notifications */
