<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Str;


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

test('invalid uuid format returns 403 on delete', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('notifications.destroy', ['notification' => 'not-a-uuid']))
        ->assertForbidden();
});
