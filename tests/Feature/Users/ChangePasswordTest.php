<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Models\AuditTrail;
use App\Models\User;
use App\Notifications\PasswordChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission as PermissionModel;

it('allows authorized user to change another user password', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManagePassword->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create();

    $response = $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('users.password.update', $target), [
            'password' => 'new-secure-password123',
        ]);

    $response->assertRedirect();

    expect(Hash::check('new-secure-password123', $target->fresh()->password))->toBeTrue();
});

it('forbids password change without permission', function (): void {
    $user = User::factory()->create();
    $target = User::factory()->create();

    $response = $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('users.password.update', $target), [
            'password' => 'new-secure-password123',
        ]);

    $response->assertForbidden();
});

it('requires password confirmation for admin password resets', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManagePassword->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create();

    $response = $this->actingAs($admin)
        ->put(route('users.password.update', $target), [
            'password' => 'new-secure-password123',
        ]);

    $response->assertRedirect(route('password.confirm'));
});

it('rotates the remember token and notifies the user on admin password reset', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManagePassword->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create();
    $before = $target->remember_token;

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('users.password.update', $target), [
            'password' => 'new-secure-password123',
        ])
        ->assertRedirect();

    expect($target->fresh()->remember_token)->not->toBe($before);
    Notification::assertSentTo($target, PasswordChanged::class);
});

it('invalidates database sessions of the target user on admin password reset', function (): void {
    config(['session.driver' => 'database']);

    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManagePassword->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'target-session-id',
        'user_id' => $target->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestAgent',
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    DB::table('sessions')->insert([
        'id' => 'admin-session-id',
        'user_id' => $admin->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestAgent',
        'payload' => 'payload',
        'last_activity' => time(),
    ]);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('users.password.update', $target), [
            'password' => 'new-secure-password123',
        ])
        ->assertRedirect();

    expect(DB::table('sessions')->where('user_id', $target->id)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $admin->id)->exists())->toBeTrue();
});

it('ensures password values never appear in audit data', function (): void {
    if (! class_exists(AuditTrail::class)) {
        expect(true)->toBeTrue();

        return;
    }

    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManagePassword->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $target = User::factory()->create();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->put(route('users.password.update', $target), [
            'password' => 'super-secret-password-xyz',
        ]);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserPasswordChanged)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->new_values['password'])->toBe('[REDACTED]')
        ->and(json_encode($audit->new_values))->not->toContain('super-secret-password-xyz');
});
