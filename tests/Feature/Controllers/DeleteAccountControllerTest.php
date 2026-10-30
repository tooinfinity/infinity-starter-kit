<?php

declare(strict_types=1);

/* @chisel-audit-trails */
use App\Enums\AuditEvent;
use App\Models\AuditTrail;
/* @end-chisel-audit-trails */
use App\Models\User;
/* @chisel-notifications */
use Illuminate\Support\Facades\DB;
/* @end-chisel-notifications */
use Illuminate\Support\Facades\Hash;
/* @chisel-notifications */
use Illuminate\Support\Str;
/* @end-chisel-notifications */
/* @chisel-user-management */
use Spatie\Permission\Models\Role;

/* @end-chisel-user-management */

it('may delete user account', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->delete(route('user.destroy'), [
            'password' => 'password',
        ]);

    $response->assertRedirectToRoute('home');

    expect($user->fresh())->toBeNull();

    $this->assertGuest();
});

it('requires password to delete account', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->delete(route('user.destroy'), []);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('password');

    expect($user->fresh())->not->toBeNull();
});

it('requires correct password to delete account', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->delete(route('user.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response->assertRedirectToRoute('user-profile.edit')
        ->assertSessionHasErrors('password');

    expect($user->fresh())->not->toBeNull();
});

/* @chisel-user-management */
it('prevents sole super admin from deleting their own account (SEC-002)', function (): void {
    $role = Role::findOrCreate(App\Enums\Role::SuperAdmin->value);
    $superAdmin = User::factory()->create([
        'password' => Hash::make('password'),
    ]);
    $superAdmin->assignRole($role);

    $response = $this->actingAs($superAdmin)
        ->fromRoute('user-profile.edit')
        ->delete(route('user.destroy'), [
            'password' => 'password',
        ]);

    $response->assertSessionHasErrors(['user' => __('Cannot delete the last Super Admin.')]);

    expect($superAdmin->fresh())->not->toBeNull();
    $this->assertAuthenticatedAs($superAdmin);
});

it('blocks self-deletion of the last active super admin even if an inactive one exists', function (): void {
    $role = Role::findOrCreate(App\Enums\Role::SuperAdmin->value);
    $actor = User::factory()->create([
        'password' => Hash::make('password'),
    ]);
    $actor->assignRole($role);
    User::factory()->create(['is_active' => false])->assignRole($role);

    $this->actingAs($actor)
        ->fromRoute('user-profile.edit')
        ->delete(route('user.destroy'), [
            'password' => 'password',
        ])
        ->assertSessionHasErrors(['user' => __('Cannot delete the last Super Admin.')]);

    expect(User::query()->whereKey($actor->id)->exists())->toBeTrue();
});
/* @end-chisel-user-management */

/* @chisel-notifications */
it('prunes notifications when user account is deleted (DATA-001)', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);
    $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'test-notification',
        'data' => [],
        'read_at' => null,
    ]);

    expect($user->notifications()->count())->toBe(1);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->delete(route('user.destroy'), [
            'password' => 'password',
        ]);

    expect(DB::table('notifications')->where('notifiable_id', $user->id)->count())->toBe(0);
});
/* @end-chisel-notifications */

/* @chisel-audit-trails */
it('records audit trail when user deletes own account (OBS-001)', function (): void {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user)
        ->fromRoute('user-profile.edit')
        ->delete(route('user.destroy'), [
            'password' => 'password',
        ]);

    $audit = AuditTrail::query()
        ->where('event', AuditEvent::UserDeleted)
        ->where('auditable_id', $user->id)
        ->first();

    expect($audit)->not->toBeNull();
});
/* @end-chisel-audit-trails */
