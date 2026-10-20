<?php

declare(strict_types=1);

use App\Actions\Users\UpdateUserRolesAction;
use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\SuperAdminRestrictionOperation;
use App\Models\User;
use App\Rules\NotLastSuperAdmin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('prevents removing super admin role from last super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('editor');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $response = $this->actingAs($superAdmin)
        ->put(route('users.update', $superAdmin), [
            'name' => $superAdmin->name,
            'email' => $superAdmin->email,
            'is_active' => true,
            'roles' => ['editor'],
        ]);

    $response->assertSessionHasErrors(['roles']);

    expect($superAdmin->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('allows removing super admin role if multiple super admins exist', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('editor');

    $superAdmin1 = User::factory()->create();
    $superAdmin1->assignRole($superAdminRole);

    $superAdmin2 = User::factory()->create();
    $superAdmin2->assignRole($superAdminRole);

    $response = $this->actingAs($superAdmin2)
        ->put(route('users.update', $superAdmin1), [
            'name' => $superAdmin1->name,
            'email' => $superAdmin1->email,
            'is_active' => true,
            'roles' => ['editor'],
        ]);

    $response->assertRedirectToRoute('users.index');
    expect($superAdmin1->fresh()->hasRole(Role::SuperAdmin->value))->toBeFalse()
        ->and($superAdmin1->fresh()->hasRole('editor'))->toBeTrue();
});

it('NotLastSuperAdmin throws validation exception when only one super admin exists', function (SuperAdminRestrictionOperation $operation): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    DB::transaction(function () use ($superAdmin, $operation): void {
        NotLastSuperAdmin::check($superAdmin, $operation);
    });
})->with([
    SuperAdminRestrictionOperation::Delete,
    SuperAdminRestrictionOperation::Deactivate,
    SuperAdminRestrictionOperation::RemoveRole,
])->throws(ValidationException::class);

it('NotLastSuperAdmin passes when multiple super admins exist', function (SuperAdminRestrictionOperation $operation): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $superAdmin1 = User::factory()->create();
    $superAdmin1->assignRole($superAdminRole);

    $superAdmin2 = User::factory()->create();
    $superAdmin2->assignRole($superAdminRole);

    DB::transaction(function () use ($superAdmin1, $operation): void {
        NotLastSuperAdmin::check($superAdmin1, $operation);
    });

    expect(true)->toBeTrue();
})->with([
    SuperAdminRestrictionOperation::Delete,
    SuperAdminRestrictionOperation::Deactivate,
    SuperAdminRestrictionOperation::RemoveRole,
]);

it('UpdateUserRolesAction allows removing super admin role if multiple super admins exist', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    RoleModel::findOrCreate('editor');

    $superAdmin1 = User::factory()->create();
    $superAdmin1->assignRole($superAdminRole);

    $superAdmin2 = User::factory()->create();
    $superAdmin2->assignRole($superAdminRole);

    $action = resolve(UpdateUserRolesAction::class);

    $result = $action->handle($superAdmin1, ['editor']);

    expect($result->id)->toBe($superAdmin1->id)
        ->and($superAdmin1->fresh()->hasRole(Role::SuperAdmin->value))->toBeFalse()
        ->and($superAdmin1->fresh()->hasRole('editor'))->toBeTrue();
});

it('UpdateUserRolesAction updates roles and records audit trail by default', function (): void {
    RoleModel::findOrCreate('editor');
    RoleModel::findOrCreate('manager');

    $user = User::factory()->create();
    $user->assignRole('editor');

    $action = resolve(UpdateUserRolesAction::class);

    $result = $action->handle($user, ['manager']);

    expect($result->id)->toBe($user->id)
        ->and($user->fresh()->hasRole('manager'))->toBeTrue()
        ->and($user->fresh()->hasRole('editor'))->toBeFalse();
});

it('UpdateUserRolesAction updates roles without recording audit trail when disabled', function (): void {
    RoleModel::findOrCreate('editor');
    RoleModel::findOrCreate('manager');

    $user = User::factory()->create();
    $user->assignRole('editor');

    $action = resolve(UpdateUserRolesAction::class);

    $result = $action->handle($user, ['manager'], recordAuditTrail: false);

    expect($result->id)->toBe($user->id)
        ->and($user->fresh()->hasRole('manager'))->toBeTrue()
        ->and($user->fresh()->hasRole('editor'))->toBeFalse();
});

it('UpdateUserRolesAction allows assigning super admin role', function (): void {
    RoleModel::findOrCreate(Role::SuperAdmin->value);

    $user = User::factory()->create();

    $action = resolve(UpdateUserRolesAction::class);

    $result = $action->handle($user, [Role::SuperAdmin->value]);

    expect($result->id)->toBe($user->id)
        ->and($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('UpdateUserRolesAction returns unpersisted user directly', function (): void {
    $unsavedUser = new User;

    $action = resolve(UpdateUserRolesAction::class);

    $result = $action->handle($unsavedUser, ['editor']);

    expect($result)->toBe($unsavedUser);
});

dataset('super-admin-targeted-operations', [
    'change email' => [Permission::UsersUpdate, 'put', 'users.update', fn (User $t): array => ['name' => $t->name, 'email' => 'attacker@example.com']],
    'change password' => [Permission::UsersManagePassword, 'put', 'users.password.update', fn (): array => ['password' => 'new-secure-password123']],
    'deactivate' => [Permission::UsersUpdate, 'patch', 'users.deactivate', fn (): array => []],
    'activate' => [Permission::UsersUpdate, 'patch', 'users.activate', fn (): array => []],
    'delete' => [Permission::UsersDelete, 'delete', 'users.destroy', fn (): array => []],
]);

it('forbids non-super-admins from managing a super admin', function (Permission $permission, string $method, string $route, Closure $payload): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);

    $target = User::factory()->create(['is_active' => true]);
    $target->assignRole($role);
    User::factory()->create()->assignRole($role); // a second super admin, so "last super admin" can't mask the result

    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    $actor->givePermissionTo(PermissionModel::findOrCreate($permission->value));

    $original = $target->only(['email', 'password', 'is_active']);

    $this->actingAs($actor)->{$method}(route($route, $target), $payload($target))->assertForbidden();

    $fresh = $target->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->only(['email', 'password', 'is_active']))->toBe($original);
})->with('super-admin-targeted-operations');

it('still lets a super admin manage another super admin', function (): void {
    $role = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = User::factory()->create();
    $target->assignRole($role);

    $this->actingAs($actor)
        ->put(route('users.password.update', $target), ['password' => 'new-secure-password123'])
        ->assertRedirect();
});

it('still lets a delegated admin manage ordinary users', function (): void {
    $actor = User::factory()->create();
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    $actor->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManagePassword->value));

    $this->actingAs($actor)
        ->put(route('users.password.update', User::factory()->create()), ['password' => 'new-secure-password123'])
        ->assertRedirect();
});

it('User canManage prevents non-super-admin from managing super admin', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $delegatedAdmin = User::factory()->create();

    expect($delegatedAdmin->canManage($superAdmin))->toBeFalse();
    expect($superAdmin->canManage($delegatedAdmin))->toBeTrue();
    expect($superAdmin->canManage($superAdmin))->toBeTrue();
});
