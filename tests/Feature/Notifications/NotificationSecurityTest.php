<?php

declare(strict_types=1);

use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Str;

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

    $this->assertModelExists($notificationB);
});

test('non-existent notification id returns 403', function (string $method, string $route): void {
    $user = User::factory()->create();
    $randomUuid = (string) Str::uuid();

    $this->actingAs($user)
        ->{$method}(route($route, ['notification' => $randomUuid]))
        ->assertForbidden();
})->with([
    'mark read' => ['patch', 'notifications.mark-read'],
    'delete' => ['delete', 'notifications.destroy'],
]);

test('invalid uuid format returns 403', function (string $method, string $route): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->{$method}(route($route, ['notification' => 'not-a-uuid']))
        ->assertForbidden();
})->with([
    'mark read' => ['patch', 'notifications.mark-read'],
    'delete' => ['delete', 'notifications.destroy'],
]);
