<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SettingKey;
use App\Http\Middleware\HandleSettings;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Support\SessionKey;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

it('renders settings page for authorized user', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('settings.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/application/edit')
            ->has('settings'));
});

it('shows stored values without the handle settings middleware', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Custom App',
    ]);

    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
    $user->givePermissionTo($permission);

    $this->actingAs($user)
        ->withoutMiddleware(HandleSettings::class)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('settings', fn ($settings): bool => $settings['application.name'] === 'Custom App'));
});

it('renders settings page with defaults and reports error when cache failure occurs', function (): void {
    Exceptions::fake();
    Cache::shouldReceive('remember')->andThrow(new RuntimeException('Cache store unavailable'));

    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('settings.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/application/edit')
            ->where('settings', [
                'application.name' => config('app.name', 'Laravel'),
                'application.timezone' => config('app.timezone', 'UTC'),
            ]));

    Exceptions::assertReported(RuntimeException::class);
});

it('forbids settings page for user without permission', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::SettingsManage->value);

    $response = $this->actingAs($user)
        ->get(route('settings.edit'));

    $response->assertForbidden();
});

it('allows super admin to access settings page', function (): void {
    $user = User::factory()->create();
    $superAdminRole = RoleModel::findOrCreate('super-admin');
    $user->assignRole($superAdminRole);

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('settings.edit'));

    $response->assertOk();
});

it('redirects unauthenticated user to login', function (): void {
    $response = $this->get(route('settings.edit'));

    $response->assertRedirect(route('login'));
});

it('returns default settings when none are stored', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('settings.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/application/edit')
            ->has('settings')
            ->where('settings', [
                'application.name' => config('app.name', 'Laravel'),
                'application.timezone' => config('app.timezone', 'UTC'),
            ]));
});

it('returns stored settings when they exist', function (): void {
    Setting::query()->create([
        'key' => SettingKey::ApplicationName->value,
        'value' => 'Custom App',
    ]);

    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->fromRoute('dashboard')
        ->get(route('settings.edit'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('settings', fn ($settings): bool => $settings['application.name'] === 'Custom App'));
});

it('may update settings', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'settings' => [
                'application.name' => 'Updated App',
                'application.timezone' => 'Europe/London',
            ],
        ]);

    $response->assertRedirectToRoute('settings.edit')
        ->assertSessionHas(SessionKey::FLASH_DATA, [
            'toast' => [
                'type' => 'success',
                'message' => __('Settings updated.'),
            ],
        ]);

    $name = Setting::query()->where('key', SettingKey::ApplicationName->value)->first();
    $timezone = Setting::query()->where('key', SettingKey::ApplicationTimezone->value)->first();

    expect($name)->not->toBeNull()
        ->and($name->value)->toBe('Updated App')
        ->and($timezone)->not->toBeNull()
        ->and($timezone->value)->toBe('Europe/London');
});

it('forbids update for user without permission', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::SettingsManage->value);

    $response = $this->actingAs($user)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'settings' => [
                'application.name' => 'Should Not Update',
            ],
        ]);

    $response->assertForbidden();
});

it('validates application name is required', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'settings' => [
                'application.name' => '',
            ],
        ]);

    $response->assertRedirect(route('settings.edit'))
        ->assertSessionHasErrors();
});

it('validates application name max length', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'settings' => [
                'application.name' => str_repeat('a', 256),
            ],
        ]);

    $response->assertRedirect(route('settings.edit'))
        ->assertSessionHasErrors();
});

it('validates timezone is a valid timezone', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'settings' => [
                'application.timezone' => 'Invalid/Timezone',
            ],
        ]);

    $response->assertRedirect(route('settings.edit'))
        ->assertSessionHasErrors();
});

it('allows partial update of settings', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'settings' => [
                'application.name' => 'Only Name Update',
            ],
        ]);

    $response->assertRedirectToRoute('settings.edit');

    $name = Setting::query()->where('key', SettingKey::ApplicationName->value)->first();

    expect($name)->not->toBeNull()
        ->and($name->value)->toBe('Only Name Update')
        ->and(Setting::query()->where('key', SettingKey::ApplicationTimezone->value)->first())->toBeNull();
});

it('rejects unknown setting keys', function (): void {
    $user = User::factory()->create();
    $permission = PermissionModel::findOrCreate(Permission::SettingsManage->value);
    $user->givePermissionTo($permission);

    $response = $this->actingAs($user)
        ->from(route('settings.edit'))
        ->put(route('settings.update'), [
            'settings' => [
                'unknown.key' => 'value',
            ],
        ]);

    $response->assertRedirect(route('settings.edit'))
        ->assertSessionHasErrors();
});
