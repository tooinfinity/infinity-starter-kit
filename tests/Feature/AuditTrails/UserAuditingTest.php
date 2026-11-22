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
use App\Models\Activity;
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

    $audit = Activity::query()->where('event', AuditEvent::UserCreated->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($actor->id)
        ->and($audit->subject_id)->toBe($createdUser->id)
        ->and($audit->attribute_changes?->get('attributes')['name'])->toBe('Jane Doe')
        ->and($audit->attribute_changes?->get('attributes')['email'])->toBe('jane@example.com')
        ->and($audit->attribute_changes?->get('attributes')['is_active'])->toBeTrue();
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

    $audit = Activity::query()->where('event', AuditEvent::UserUpdated->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($actor->id)
        ->and($audit->subject_id)->toBe($targetUser->id)
        ->and($audit->attribute_changes?->get('old')['name'])->toBe('Original Name')
        ->and($audit->attribute_changes?->get('old')['email'])->toBe('orig@example.com')
        ->and($audit->attribute_changes?->get('attributes')['name'])->toBe('Updated Name')
        ->and($audit->attribute_changes?->get('attributes')['email'])->toBe('updated@example.com');
});

test('UpdateUser produces exactly one activity_log row with user_updated event and no duplicate model events', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'orig@example.com',
    ]);

    Activity::query()->delete();

    $action = resolve(AdminUpdateUser::class);
    $action->handle($targetUser, new UpdateUserData(
        name: 'New Name',
        email: 'new@example.com',
        roles: [],
    ));

    expect(Activity::query()->count())->toBe(1)
        ->and(Activity::query()->where('event', AuditEvent::UserUpdated->value)->count())->toBe(1)
        ->and(Activity::query()->where('event', 'updated')->count())->toBe(0);
});

test('profile UpdateUser action produces exactly one activity_log row with user_updated event', function (): void {
    $user = User::factory()->create([
        'name' => 'Self Name',
        'email' => 'self@example.com',
    ]);
    $this->actingAs($user);

    Activity::query()->delete();

    $action = resolve(UpdateUser::class);
    $action->handle($user, [
        'name' => 'Self Updated',
        'email' => 'self_updated@example.com',
    ]);

    expect(Activity::query()->count())->toBe(1)
        ->and(Activity::query()->where('event', AuditEvent::UserUpdated->value)->count())->toBe(1)
        ->and(Activity::query()->where('event', 'updated')->count())->toBe(0);
});

test('UpdateUserStatus (activate) records user.activated audit trail', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->inactive()->create();

    $action = resolve(UpdateUserStatus::class);
    $action->handle($targetUser, true);

    $audit = Activity::query()->where('event', AuditEvent::UserActivated->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($actor->id)
        ->and($audit->subject_id)->toBe($targetUser->id)
        ->and($audit->attribute_changes?->get('old')['is_active'])->toBeFalse()
        ->and($audit->attribute_changes?->get('attributes')['is_active'])->toBeTrue();
});

test('UpdateUserStatus (deactivate) records user.deactivated audit trail', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create(['is_active' => true]);

    $action = resolve(UpdateUserStatus::class);
    $action->handle($targetUser, false, $actor);

    $audit = Activity::query()->where('event', AuditEvent::UserDeactivated->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($actor->id)
        ->and($audit->subject_id)->toBe($targetUser->id)
        ->and($audit->attribute_changes?->get('old')['is_active'])->toBeTrue()
        ->and($audit->attribute_changes?->get('attributes')['is_active'])->toBeFalse();
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

    $audit = Activity::query()->where('event', AuditEvent::UserDeleted->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($actor->id)
        ->and($audit->getProperty('actor.name'))->toBe($actor->name)
        ->and($audit->subject_id)->toBe($targetId)
        ->and($audit->attribute_changes?->get('old'))->not->toHaveKey('name')
        ->and($audit->attribute_changes?->get('old'))->not->toHaveKey('email')
        ->and($audit->attribute_changes?->get('old')['is_active'])->toBeTrue()
        ->and($audit->getProperty('tags'))->toContain('anonymized');
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

    $audit = Activity::query()->where('event', AuditEvent::UserDeleted->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($actor->id)
        ->and($audit->subject_id)->toBe($targetId)
        ->and($audit->attribute_changes?->get('old')['name'])->toBe('To Be Retained')
        ->and($audit->attribute_changes?->get('old')['email'])->toBe('retain_me@example.com');
});

test('UpdateUserPassword records user.password_changed audit trail with redacted password', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    $targetUser = User::factory()->create();

    $action = resolve(AdminUpdateUserPassword::class);
    $action->handle($targetUser, 'brand_new_secret_password');

    $audit = Activity::query()->where('event', AuditEvent::UserPasswordChanged->value)->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($actor->id)
        ->and($audit->subject_id)->toBe($targetUser->id)
        ->and($audit->attribute_changes?->get('attributes')['password'])->toBe('[REDACTED]')
        ->and(json_encode($audit->attribute_changes?->get('attributes')))->not->toContain('brand_new_secret_password');
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

    $audit = Activity::query()
        ->where('event', AuditEvent::UserUpdated->value)
        ->where('subject_id', $user->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->attribute_changes?->get('attributes')['name'])->toBe('New Self')
        ->and($audit->attribute_changes?->get('attributes')['email'])->toBe('new_self@example.com')
        ->and($audit->attribute_changes?->get('attributes')['is_active'])->toBeTrue()
        ->and($audit->attribute_changes?->get('attributes')['roles'])->toBe([])
        ->and($audit->attribute_changes?->get('old')['name'])->toBe('Old Self')
        ->and($audit->attribute_changes?->get('old')['email'])->toBe('old_self@example.com')
        ->and($audit->attribute_changes?->get('old')['is_active'])->toBeTrue()
        ->and($audit->attribute_changes?->get('old')['roles'])->toBe([]);
});

/* @chisel-password-update */
test('UpdateUserPassword self-service action records user.password_changed audit trail (OBS-001)', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $action = resolve(UpdateUserPassword::class);
    $action->handle($user, 'new_secret_pass_123');

    $audit = Activity::query()
        ->where('event', AuditEvent::UserPasswordChanged->value)
        ->where('subject_id', $user->id)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($user->id)
        ->and($audit->attribute_changes?->get('attributes')['password'])->toBe('[REDACTED]')
        ->and(json_encode($audit->attribute_changes?->get('attributes')))->not->toContain('new_secret_pass_123');
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

    $audit = Activity::query()
        ->where('event', AuditEvent::UserDeleted->value)
        ->where('subject_id', $userId)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->subject_id)->toBe($userId)
        ->and($audit->causer_id)->toBeNull()
        ->and($audit->getProperty('actor.name'))->toBeNull()
        ->and($audit->getProperty('actor.email'))->toBeNull()
        ->and($audit->ip_address)->toBeNull()
        ->and($audit->user_agent)->toBeNull()
        ->and($audit->getProperty('tags'))->toContain('anonymized')
        ->and($audit->attribute_changes?->get('old'))->not->toHaveKey('name')
        ->and($audit->attribute_changes?->get('old'))->not->toHaveKey('email')
        ->and($audit->attribute_changes?->get('old')['is_active'])->toBeTrue();
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

    $audit = Activity::query()
        ->where('event', AuditEvent::UserDeleted->value)
        ->where('subject_id', $userId)
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->subject_id)->toBe($userId)
        ->and($audit->causer_id)->toBeNull()
        ->and($audit->getProperty('actor.name'))->toBe('Self Retained')
        ->and($audit->getProperty('actor.email'))->toBe('self_retained@example.com')
        ->and($audit->attribute_changes?->get('old')['name'])->toBe('Self Retained')
        ->and($audit->attribute_changes?->get('old')['email'])->toBe('self_retained@example.com')
        ->and($audit->attribute_changes?->get('old')['is_active'])->toBeTrue()
        ->and($audit->attribute_changes?->get('old')['roles'])->toBe([]);
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

    $adminAudit = Activity::query()->where('event', AuditEvent::UserUpdated->value)->where('subject_id', $targetForAdmin->id)->firstOrFail();
    $selfAudit = Activity::query()->where('event', AuditEvent::UserUpdated->value)->where('subject_id', $selfUser->id)->firstOrFail();

    $expectedKeys = ['name', 'email', 'is_active', 'roles'];
    sort($expectedKeys);

    expect(collect(array_keys($adminAudit->attribute_changes?->get('old') ?? []))->sort()->values()->all())->toBe($expectedKeys)
        ->and(collect(array_keys($selfAudit->attribute_changes?->get('old') ?? []))->sort()->values()->all())->toBe($expectedKeys)
        ->and(collect(array_keys($adminAudit->attribute_changes?->get('attributes') ?? []))->sort()->values()->all())->toBe($expectedKeys)
        ->and(collect(array_keys($selfAudit->attribute_changes?->get('attributes') ?? []))->sort()->values()->all())->toBe($expectedKeys);
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

    $adminAudit = Activity::query()->where('event', AuditEvent::UserDeleted->value)->where('subject_id', $adminId)->firstOrFail();
    $selfAudit = Activity::query()->where('event', AuditEvent::UserDeleted->value)->where('subject_id', $selfId)->firstOrFail();

    $expectedKeys = ['name', 'email', 'is_active', 'roles'];
    sort($expectedKeys);

    expect(collect(array_keys($adminAudit->attribute_changes?->get('old') ?? []))->sort()->values()->all())->toBe($expectedKeys)
        ->and(collect(array_keys($selfAudit->attribute_changes?->get('old') ?? []))->sort()->values()->all())->toBe($expectedKeys);
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

    $audit = Activity::query()
        ->where('event', AuditEvent::UserRoleChanged->value)
        ->where('subject_id', $targetUser->id)
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->causer_id)->toBe($actor->id)
        ->and($audit->subject_id)->toBe($targetUser->id)
        ->and($audit->getProperty('tags'))->toBe(['users', 'roles'])
        ->and($audit->attribute_changes?->get('old')['roles'])->toBe(['editor'])
        ->and($audit->attribute_changes?->get('attributes')['roles'])->toBe(['manager']);
});

test('UpdateUserRoles does not record user.role_changed audit trail when roles do not change', function (): void {
    $actor = User::factory()->create();
    $this->actingAs($actor);

    RoleModel::findOrCreate('editor');

    $targetUser = User::factory()->create();
    $targetUser->assignRole('editor');

    $action = resolve(UpdateUserRoles::class);
    $action->handle($targetUser, ['editor']);

    expect(Activity::query()->where('event', AuditEvent::UserRoleChanged->value)->count())->toBe(0);
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

    $roleAudit = Activity::query()->where('event', AuditEvent::UserRoleChanged->value)->first();
    $userAudit = Activity::query()->where('event', AuditEvent::UserUpdated->value)->first();

    expect($roleAudit)->not->toBeNull()
        ->and($roleAudit->attribute_changes?->get('old')['roles'])->toBe(['editor'])
        ->and($roleAudit->attribute_changes?->get('attributes')['roles'])->toBe(['manager'])
        ->and($userAudit)->not->toBeNull()
        ->and($userAudit->attribute_changes?->get('old')['name'])->toBe('Initial Name')
        ->and($userAudit->attribute_changes?->get('attributes')['name'])->toBe('New Name');
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

    expect(Activity::query()->where('event', AuditEvent::UserRoleChanged->value)->count())->toBe(0);

    $userAudit = Activity::query()->where('event', AuditEvent::UserUpdated->value)->first();
    expect($userAudit)->not->toBeNull()
        ->and($userAudit->attribute_changes?->get('old')['name'])->toBe('Initial Name')
        ->and($userAudit->attribute_changes?->get('attributes')['name'])->toBe('New Name');
});
