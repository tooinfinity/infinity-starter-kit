<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Exceptions\RoleDoesNotExist;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;
use Spatie\Permission\PermissionRegistrar;

test('super admin bypasses all gate checks', function (): void {
    $user = User::factory()->create();
    $superAdminRole = RoleModel::findOrCreate(Role::SuperAdmin->value);
    $user->assignRole($superAdminRole);

    expect(Gate::forUser($user)->allows('non-existent-permission'))->toBeTrue();
});

test('normal user is denied without required permission', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::UsersView->value);

    expect(Gate::forUser($user)->allows(Permission::UsersView->value))->toBeFalse();
});

test('user with permission is authorized via gate', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::UsersCreate->value);
    $user->givePermissionTo($permission);

    expect(Gate::forUser($user)->allows(Permission::UsersCreate->value))->toBeTrue();
});

test('user with role permission is authorized via gate', function (): void {
    $user = User::factory()->create();
    $role = RoleModel::findOrCreate('editor');
    $permission = PermissionModel::findOrCreate(Permission::UsersUpdate->value);
    $role->givePermissionTo($permission);
    $user->assignRole($role);

    expect(Gate::forUser($user)->allows(Permission::UsersUpdate->value))->toBeTrue();
});

test('authorization setup command creates permissions and roles', function (): void {
    $this->artisan('authorization:setup')
        ->assertSuccessful();

    foreach (Permission::values() as $permissionName) {
        expect(PermissionModel::findByName($permissionName))->not->toBeNull();
    }

    $superAdmin = RoleModel::findByName(Role::SuperAdmin->value);
    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->permissions)->toHaveCount(count(Permission::cases()));
});

test('authorization setup command is idempotent and keeps permission cache accurate', function (): void {
    $this->artisan('authorization:setup')->assertSuccessful();
    $this->artisan('authorization:setup')->assertSuccessful();

    expect(PermissionModel::all())->toHaveCount(count(Permission::cases()))
        ->and(RoleModel::query()->where('name', Role::SuperAdmin->value)->count())->toBe(1);

    $superAdmin = RoleModel::findByName(Role::SuperAdmin->value);
    expect($superAdmin->permissions->pluck('name')->sort()->values()->toArray())
        ->toBe(collect(Permission::values())->sort()->values()->all());

    $cachedPermissions = resolve(PermissionRegistrar::class)
        ->getPermissions()
        ->pluck('name')
        ->sort()
        ->values()
        ->toArray();

    expect($cachedPermissions)
        ->toBe(collect(Permission::values())->sort()->values()->all());
});

test('authorization setup command synchronizes permissions on super admin role', function (): void {
    $this->artisan('authorization:setup')->assertSuccessful();

    $superAdmin = RoleModel::findByName(Role::SuperAdmin->value);

    expect($superAdmin->permissions->pluck('name')->sort()->values()->toArray())
        ->toBe(collect(Permission::values())->sort()->values()->all());
});

test('assigning super admin role fails on fresh database before authorization setup is run', function (): void {
    $user = User::factory()->create();

    expect(fn () => $user->assignRole(Role::SuperAdmin->value))
        ->toThrow(RoleDoesNotExist::class);
});

test('fresh database bootstrap with authorization setup and first admin creation works end to end', function (): void {
    $this->artisan('authorization:setup')->assertSuccessful();
    $this->artisan('authorization:setup')->assertSuccessful();

    $user = User::query()->create([
        'name' => 'Administrator',
        'email' => 'admin@example.com',
        'password' => 'secure-password',
        'email_verified_at' => now(),
    ]);
    $user->assignRole(Role::SuperAdmin->value);

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and(Gate::forUser($user)->allows('non-existent-permission'))->toBeTrue();
});

test('documented first admin promotion by email works end to end on fresh database', function (): void {
    $this->artisan('authorization:setup')->assertSuccessful();

    $user = User::query()->create([
        'name' => 'Regular User',
        'email' => 'registered@example.com',
        'password' => 'secure-password',
        'email_verified_at' => now(),
    ]);

    User::query()
        ->where('email', 'registered@example.com')
        ->firstOrFail()
        ->assignRole(Role::SuperAdmin->value);

    expect($user->fresh()->hasRole(Role::SuperAdmin->value))->toBeTrue()
        ->and(Gate::forUser($user)->allows(Permission::UsersView->value))->toBeTrue();
});

test('shared inertia props include authorization data for authenticated user', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::UsersView->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->get(route('dashboard'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('auth.permissions')
            ->has('auth.roles')
            ->where('auth.permissions', [Permission::UsersView->value])
        );
});

test('shared inertia props return empty arrays for guests', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.permissions', [])
            ->where('auth.roles', [])
        );
});

test('starter kit source repository contains both setup commands', function (): void {
    $authCommand = 'app/Console/Commands/SetupAuthorizationCommand.php';
    $adminCommand = implode('', ['app/Console/Commands/', 'SetupAdmin', 'UserCommand.php']);

    expect(file_exists(base_path($authCommand)))->toBeTrue()
        ->and(file_exists(base_path($adminCommand)))->toBeTrue();
});

test('authorization seeder does not exist and is not referenced', function (): void {
    expect(file_exists(base_path('database/seeders/AuthorizationSeeder.php')))->toBeFalse()
        ->and(class_exists('Database\Seeders\AuthorizationSeeder'))->toBeFalse();
});

test('uses a non-integer model_id column in the Spatie pivot tables', function (string $table): void {
    $column = collect(Schema::getColumns($table))->firstWhere('name', 'model_id');

    expect($column)->not->toBeNull()
        ->and($column['type_name'])->not->toContain('int');
})->with(['model_has_roles', 'model_has_permissions']);

test('stores the UUID user id as the pivot model_id', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleModel::findOrCreate(Role::SuperAdmin->value));
    $user->givePermissionTo(PermissionModel::findOrCreate(Permission::UsersView->value));

    expect(DB::table('model_has_roles')->value('model_id'))->toBe($user->id)
        ->and(DB::table('model_has_permissions')->value('model_id'))->toBe($user->id);
});
