<?php

declare(strict_types=1);

use App\Actions\DeleteUser;
use App\Actions\UpdateUser;
/* @chisel-password-update */
use App\Actions\UpdateUserPassword;
/* @end-chisel-password-update */
use App\Actions\Users\CreateUser as AdminCreateUser;
use App\Actions\Users\DeleteUser as AdminDeleteUser;
use App\Actions\Users\UpdateUser as AdminUpdateUser;
use App\Actions\Users\UpdateUserPassword as AdminUpdateUserPassword;
use App\Actions\Users\UpdateUserRoles;
use App\Actions\Users\UpdateUserStatus;
use App\Data\Users\CreateUserData;
use App\Data\Users\UpdateUserData;
use App\Enums\AuditEvent;
use App\Models\AuditTrail;
use App\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

test('CreateUser records user.created audit trail', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $action = resolve(AdminCreateUser::class);
    $data = new CreateUserData(
        name: 'Jane Doe',
        email: 'jane@example.com',
        password: 'password123',
        isActive: true,
        roles: [],
    );

    $createdUser = $action->handle($data);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserCreated)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($createdUser->id)
        ->and($audit->new_values['name'])->toBe('Jane Doe')
        ->and($audit->new_values['email'])->toBe('jane@example.com')
        ->and($audit->new_values['is_active'])->toBeTrue();
});

test('UpdateUser records user.updated audit trail with diff', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'orig@example.com',
        'is_active' => true,
    ]);

    $action = resolve(AdminUpdateUser::class);
    $data = new UpdateUserData(
        name: 'Updated Name',
        email: 'updated@example.com',
        roles: [],
    );

    $action->handle($targetUser, $data);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserUpdated)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($targetUser->id)
        ->and($audit->old_values['name'])->toBe('Original Name')
        ->and($audit->old_values['email'])->toBe('orig@example.com')
        ->and($audit->new_values['name'])->toBe('Updated Name')
        ->and($audit->new_values['email'])->toBe('updated@example.com');
});

test('UpdateUserStatus (activate) records user.activated audit trail', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->inactive()->create();

    $action = resolve(UpdateUserStatus::class);
    $action->handle($targetUser, true);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserActivated)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($targetUser->id)
        ->and($audit->old_values['is_active'])->toBeFalse()
        ->and($audit->new_values['is_active'])->toBeTrue();
});

test('UpdateUserStatus (deactivate) records user.deactivated audit trail', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create(['is_active' => true]);

    $action = resolve(UpdateUserStatus::class);
    $action->handle($targetUser, false, $actor);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserDeactivated)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($targetUser->id)
        ->and($audit->old_values['is_active'])->toBeTrue()
        ->and($audit->new_values['is_active'])->toBeFalse();
});

test('DeleteUser records user.deleted audit trail and anonymizes target PII', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create([
        'name' => 'To Be Deleted',
        'email' => 'delete_me@example.com',
    ]);
    $targetId = $targetUser->id;

    $action = resolve(AdminDeleteUser::class);
    $action->handle($targetUser, $actor);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserDeleted)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->actor_name)->toBe($actor->name)
        ->and($audit->auditable_id)->toBe($targetId)
        ->and($audit->old_values)->not->toHaveKey('name')
        ->and($audit->old_values)->not->toHaveKey('email')
        ->and($audit->old_values['is_active'])->toBeTrue()
        ->and($audit->tags)->toContain('anonymized');
});

test('DeleteUser retains target name and email when anonymization is disabled', function (): void {
    config()->set('audit.anonymize_on_delete', false);

    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create([
        'name' => 'To Be Retained',
        'email' => 'retain_me@example.com',
    ]);
    $targetId = $targetUser->id;

    $action = resolve(AdminDeleteUser::class);
    $action->handle($targetUser, $actor);

    $audit = AuditTrail::query()->where('event', AuditEvent::UserDeleted)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($targetId)
        ->and($audit->old_values['name'])->toBe('To Be Retained')
        ->and($audit->old_values['email'])->toBe('retain_me@example.com');
});

test('UpdateUserPassword records user.password_changed audit trail with redacted password', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create();

    $action = resolve(AdminUpdateUserPassword::class);
    $action->handle($targetUser, 'brand_new_secret_password');

    $audit = AuditTrail::query()->where('event', AuditEvent::UserPasswordChanged)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($targetUser->id)
        ->and($audit->new_values['password'])->toBe('[REDACTED]')
        ->and(json_encode($audit->new_values))->not->toContain('brand_new_secret_password');
});

test('UpdateUser self-service action records user.updated audit trail (OBS-001)', function (): void {
    $user = User::factory()->create([
        'name' => 'Old Self',
        'email' => 'old_self@example.com',
    ]);
    $this->actingAs($user);

    $action = resolve(UpdateUser::class);
    $action->handle($user, [
        'name' => 'New Self',
        'email' => 'new_self@example.com',
    ]);

    $audit = AuditTrail::query()
        ->where('event', AuditEvent::UserUpdated)
        ->where('auditable_id', $user->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($user->id)
        ->and($audit->new_values['name'])->toBe('New Self')
        ->and($audit->new_values['email'])->toBe('new_self@example.com')
        ->and($audit->new_values['is_active'])->toBeTrue()
        ->and($audit->new_values['roles'])->toBe([])
        ->and($audit->old_values['name'])->toBe('Old Self')
        ->and($audit->old_values['email'])->toBe('old_self@example.com')
        ->and($audit->old_values['is_active'])->toBeTrue()
        ->and($audit->old_values['roles'])->toBe([]);
});

/* @chisel-password-update */
test('UpdateUserPassword self-service action records user.password_changed audit trail (OBS-001)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $action = resolve(UpdateUserPassword::class);
    $action->handle($user, 'new_secret_pass_123');

    $audit = AuditTrail::query()
        ->where('event', AuditEvent::UserPasswordChanged)
        ->where('auditable_id', $user->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($user->id)
        ->and($audit->new_values['password'])->toBe('[REDACTED]')
        ->and(json_encode($audit->new_values))->not->toContain('new_secret_pass_123');
});
/* @end-chisel-password-update */

test('DeleteUser self-service action records user.deleted audit trail and anonymizes actor PII (OBS-001)', function (): void {
    $user = User::factory()->create([
        'name' => 'Self Deleted',
        'email' => 'self_deleted@example.com',
    ]);
    $userId = $user->id;
    $this->actingAs($user);

    $action = resolve(DeleteUser::class);
    $action->handle($user);

    $audit = AuditTrail::query()
        ->where('event', AuditEvent::UserDeleted)
        ->where('auditable_id', $userId)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->auditable_id)->toBe($userId)
        ->and($audit->user_id)->toBeNull()
        ->and($audit->actor_name)->toBeNull()
        ->and($audit->actor_email)->toBeNull()
        ->and($audit->ip_address)->toBeNull()
        ->and($audit->user_agent)->toBeNull()
        ->and($audit->tags)->toContain('anonymized')
        ->and($audit->old_values)->not->toHaveKey('name')
        ->and($audit->old_values)->not->toHaveKey('email')
        ->and($audit->old_values['is_active'])->toBeTrue();
});

test('DeleteUser self-service action retains name and email when anonymization is disabled', function (): void {
    config()->set('audit.anonymize_on_delete', false);

    $user = User::factory()->create([
        'name' => 'Self Retained',
        'email' => 'self_retained@example.com',
    ]);
    $userId = $user->id;
    $this->actingAs($user);

    $action = resolve(DeleteUser::class);
    $action->handle($user);

    $audit = AuditTrail::query()
        ->where('event', AuditEvent::UserDeleted)
        ->where('auditable_id', $userId)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->auditable_id)->toBe($userId)
        ->and($audit->user_id)->toBeNull()
        ->and($audit->actor_name)->toBe('Self Retained')
        ->and($audit->actor_email)->toBe('self_retained@example.com')
        ->and($audit->old_values['name'])->toBe('Self Retained')
        ->and($audit->old_values['email'])->toBe('self_retained@example.com')
        ->and($audit->old_values['is_active'])->toBeTrue()
        ->and($audit->old_values['roles'])->toBe([]);
});

test('user.updated audit payload has identical keys between self-service and admin paths', function (): void {
    $admin = User::factory()->create();
    $targetForAdmin = User::factory()->create(['name' => 'Admin Target', 'email' => 'admin_target@example.com']);
    $selfUser = User::factory()->create(['name' => 'Self Target', 'email' => 'self_target@example.com']);

    $this->actingAs($admin);
    resolve(AdminUpdateUser::class)->handle($targetForAdmin, new UpdateUserData(
        name: 'Admin Updated',
        email: 'admin_updated@example.com',
        roles: [],
    ));

    $this->actingAs($selfUser);
    resolve(UpdateUser::class)->handle($selfUser, [
        'name' => 'Self Updated',
        'email' => 'self_updated@example.com',
    ]);

    $adminAudit = AuditTrail::query()->where('event', AuditEvent::UserUpdated)->where('auditable_id', $targetForAdmin->id)->firstOrFail();
    $selfAudit = AuditTrail::query()->where('event', AuditEvent::UserUpdated)->where('auditable_id', $selfUser->id)->firstOrFail();

    $expectedKeys = ['name', 'email', 'is_active', 'roles'];
    sort($expectedKeys);

    expect(collect(array_keys($adminAudit->old_values))->sort()->values()->all())->toBe($expectedKeys)
        ->and(collect(array_keys($selfAudit->old_values))->sort()->values()->all())->toBe($expectedKeys)
        ->and(collect(array_keys($adminAudit->new_values))->sort()->values()->all())->toBe($expectedKeys)
        ->and(collect(array_keys($selfAudit->new_values))->sort()->values()->all())->toBe($expectedKeys);
});

test('user.deleted audit payload has identical keys between self-service and admin paths', function (): void {
    config()->set('audit.anonymize_on_delete', false);

    $admin = User::factory()->create();
    $targetForAdmin = User::factory()->create(['name' => 'Admin Target', 'email' => 'admin_del@example.com']);
    $selfUser = User::factory()->create(['name' => 'Self Target', 'email' => 'self_del@example.com']);

    $adminId = $targetForAdmin->id;
    $selfId = $selfUser->id;

    $this->actingAs($admin);
    resolve(AdminDeleteUser::class)->handle($targetForAdmin, $admin);

    $this->actingAs($selfUser);
    resolve(DeleteUser::class)->handle($selfUser);

    $adminAudit = AuditTrail::query()->where('event', AuditEvent::UserDeleted)->where('auditable_id', $adminId)->firstOrFail();
    $selfAudit = AuditTrail::query()->where('event', AuditEvent::UserDeleted)->where('auditable_id', $selfId)->firstOrFail();

    $expectedKeys = ['name', 'email', 'is_active', 'roles'];
    sort($expectedKeys);

    expect(collect(array_keys($adminAudit->old_values))->sort()->values()->all())->toBe($expectedKeys)
        ->and(collect(array_keys($selfAudit->old_values))->sort()->values()->all())->toBe($expectedKeys);
});

test('UpdateUserRoles records user.role_changed audit trail with roles and tags', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    RoleModel::findOrCreate('editor');
    RoleModel::findOrCreate('manager');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('editor');

    $action = resolve(UpdateUserRoles::class);
    $action->handle($targetUser, ['manager']);

    $audit = AuditTrail::query()
        ->where('event', AuditEvent::UserRoleChanged)
        ->where('auditable_id', $targetUser->id)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->user_id)->toBe($actor->id)
        ->and($audit->auditable_id)->toBe($targetUser->id)
        ->and($audit->tags)->toBe(['users', 'roles'])
        ->and($audit->old_values['roles'])->toBe(['editor'])
        ->and($audit->new_values['roles'])->toBe(['manager']);
});

test('UpdateUserRoles does not record user.role_changed audit trail when roles do not change', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    RoleModel::findOrCreate('editor');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('editor');

    $action = resolve(UpdateUserRoles::class);
    $action->handle($targetUser, ['editor']);

    expect(AuditTrail::query()->where('event', AuditEvent::UserRoleChanged)->count())->toBe(0);
});

test('UpdateUser records both user.updated and user.role_changed when roles change', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    RoleModel::findOrCreate('editor');
    RoleModel::findOrCreate('manager');

    $targetUser = User::factory()->create(['name' => 'Initial Name', 'email' => 'initial@example.com']);
    $targetUser->assignRole('editor');

    $action = resolve(AdminUpdateUser::class);
    $data = new UpdateUserData(
        name: 'New Name',
        email: 'new@example.com',
        roles: ['manager'],
    );
    $action->handle($targetUser, $data);

    $roleAudit = AuditTrail::query()->where('event', AuditEvent::UserRoleChanged)->first();
    $userAudit = AuditTrail::query()->where('event', AuditEvent::UserUpdated)->first();

    expect($roleAudit)->not->toBeNull()
        ->and($roleAudit->old_values['roles'])->toBe(['editor'])
        ->and($roleAudit->new_values['roles'])->toBe(['manager'])
        ->and($userAudit)->not->toBeNull()
        ->and($userAudit->old_values['name'])->toBe('Initial Name')
        ->and($userAudit->new_values['name'])->toBe('New Name');
});

test('UpdateUser records only user.updated when roles do not change', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    RoleModel::findOrCreate('editor');

    $targetUser = User::factory()->create(['name' => 'Initial Name', 'email' => 'initial@example.com']);
    $targetUser->assignRole('editor');

    $action = resolve(AdminUpdateUser::class);
    $data = new UpdateUserData(
        name: 'New Name',
        email: 'new@example.com',
        roles: ['editor'],
    );
    $action->handle($targetUser, $data);

    expect(AuditTrail::query()->where('event', AuditEvent::UserRoleChanged)->count())->toBe(0);

    $userAudit = AuditTrail::query()->where('event', AuditEvent::UserUpdated)->first();
    expect($userAudit)->not->toBeNull()
        ->and($userAudit->old_values['name'])->toBe('Initial Name')
        ->and($userAudit->new_values['name'])->toBe('New Name');
});
