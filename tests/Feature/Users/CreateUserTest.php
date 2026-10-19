<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('renders create user page for authorized user', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersCreate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($admin)
        ->get(route('users.create'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page->component('users/create'));
});

it('passes database roles to create user page when roles exist', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersCreate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    RoleModel::findOrCreate('custom-role');

    $response = $this->actingAs($admin)
        ->get(route('users.create'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/create')
            ->where('availableRoles', fn ($roles): bool => $roles->contains('custom-role')));
});

it('creates new user with hashed password and roles', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersCreate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));
    RoleModel::findOrCreate('editor');

    $response = $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'is_active' => true,
            'roles' => ['editor'],
        ]);

    $response->assertRedirectToRoute('users.index');

    $newUser = User::query()->where('email', 'newuser@example.com')->first();
    expect($newUser)->not->toBeNull()
        ->and($newUser->name)->toBe('New User')
        ->and($newUser->is_active)->toBeTrue()
        ->and(Hash::check('password123', $newUser->password))->toBeTrue()
        ->and($newUser->hasRole('editor'))->toBeTrue();
});

it('validates email uniqueness on user creation', function (): void {
    User::factory()->create(['email' => 'existing@example.com']);
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersCreate->value));
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $response = $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Duplicate Email',
            'email' => 'existing@example.com',
            'password' => 'password123',
        ]);

    $response->assertSessionHasErrors(['email']);
});

it('ignores roles when creator lacks users.manage-roles permission', function (): void {
    $creator = User::factory()->create();
    $creator->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersCreate->value));
    $creator->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $role = RoleModel::findOrCreate('editor');

    $response = $this->actingAs($creator)
        ->post(route('users.store'), [
            'name' => 'Role Ignored User',
            'email' => 'roleignored@example.com',
            'password' => 'password123',
            'roles' => [$role->name],
        ]);

    $response->assertRedirectToRoute('users.index');

    $createdUser = User::query()->where('email', 'roleignored@example.com')->first();
    expect($createdUser)->not->toBeNull()
        ->and($createdUser->roles)->toBeEmpty();
});

it('rejects assigning super-admin role when creator is not super-admin', function (): void {
    $creator = User::factory()->create();
    $creator->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersCreate->value));
    $creator->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));
    $creator->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersManageRoles->value));
    RoleModel::findOrCreate(Role::SuperAdmin->value);

    $response = $this->actingAs($creator)
        ->post(route('users.store'), [
            'name' => 'Escalated User',
            'email' => 'escalated@example.com',
            'password' => 'password123',
            'roles' => [Role::SuperAdmin->value],
        ]);

    $response->assertSessionHasErrors(['roles']);

    expect(User::query()->where('email', 'escalated@example.com')->exists())->toBeFalse();
});

it('rejects assigning a role that grants permissions creator lacks', function (): void {
    $ops = RoleModel::findOrCreate('ops');
    $ops->givePermissionTo(PermissionModel::findOrCreate(Permission::SettingsManage->value));

    $creator = User::factory()->create();
    foreach ([Permission::UsersView, Permission::UsersCreate, Permission::UsersManageRoles] as $p) {
        $creator->givePermissionTo(PermissionModel::findOrCreate($p->value));
    }

    $response = $this->actingAs($creator)
        ->post(route('users.store'), [
            'name' => 'Escalated User',
            'email' => 'escalated@example.com',
            'password' => 'password123',
            'roles' => ['ops'],
        ]);

    $response->assertSessionHasErrors('roles');

    expect(User::query()->where('email', 'escalated@example.com')->exists())->toBeFalse();
});

it("allows assigning a role whose permissions are a subset of creator's", function (): void {
    $viewer = RoleModel::findOrCreate('viewer');
    $viewer->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    $creator = User::factory()->create();
    foreach ([Permission::UsersView, Permission::UsersCreate, Permission::UsersManageRoles] as $p) {
        $creator->givePermissionTo(PermissionModel::findOrCreate($p->value));
    }

    $response = $this->actingAs($creator)
        ->post(route('users.store'), [
            'name' => 'Viewer User',
            'email' => 'viewer@example.com',
            'password' => 'password123',
            'roles' => ['viewer'],
        ]);

    $response->assertRedirectToRoute('users.index');

    $created = User::query()->where('email', 'viewer@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->hasRole('viewer'))->toBeTrue();
});

it('allows super admin to assign any role when creating user', function (): void {
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole($superAdminRole);

    $ops = RoleModel::findOrCreate('ops');
    $ops->givePermissionTo(PermissionModel::findOrCreate(Permission::SettingsManage->value));

    $response = $this->actingAs($superAdmin)
        ->post(route('users.store'), [
            'name' => 'Ops User',
            'email' => 'ops@example.com',
            'password' => 'password123',
            'roles' => ['ops'],
        ]);

    $response->assertRedirectToRoute('users.index');

    $created = User::query()->where('email', 'ops@example.com')->first();
    expect($created)->not->toBeNull()
        ->and($created->hasRole('ops'))->toBeTrue();
});
