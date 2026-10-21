<?php

declare(strict_types=1);

use App\Actions\Users\ChangeUserPasswordAction;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('ChangeUserPasswordAction changes user password, rotates remember token, and sends notification', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $beforeToken = $user->remember_token;

    $action = resolve(ChangeUserPasswordAction::class);
    $action->handle($user, 'new-secret-password123');

    $freshUser = $user->fresh();

    expect(Hash::check('new-secret-password123', $freshUser->password))->toBeTrue()
        ->and($freshUser->remember_token)->not->toBe($beforeToken);

    Notification::assertSentTo($user, PasswordChanged::class);
});

test('ChangeUserPasswordAction deletes user sessions when database session driver is used', function (): void {
    config(['session.driver' => 'database']);

    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'user-session',
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Agent',
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    DB::table('sessions')->insert([
        'id' => 'other-session',
        'user_id' => $otherUser->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Agent',
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    $action = resolve(ChangeUserPasswordAction::class);
    $action->handle($user, 'new-secret-password123');

    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $otherUser->id)->exists())->toBeTrue();
});
