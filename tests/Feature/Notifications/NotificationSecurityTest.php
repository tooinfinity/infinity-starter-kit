<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;


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
