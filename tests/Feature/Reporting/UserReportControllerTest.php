<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

test('unauthenticated user cannot access user report', function (): void {
    $this->get(route('reports.users'))
        ->assertRedirect(route('login'));
});

test('user without reports.view cannot access user report', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.users'))
        ->assertForbidden();
});

test('authorized user can view user report with valid props', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsView->value);

    $this->actingAs($user)
        ->get(route('reports.users'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('reports/users')
            ->has('summary')
            ->has('series')
            ->has('users')
            ->has('filters')
        );
});

test('user report validates filter parameters', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsView->value);

    // Invalid date_from
    $this->actingAs($user)
        ->get(route('reports.users', ['date_from' => 'invalid-date']))
        ->assertSessionHasErrors('date_from');

    // date_to before date_from
    $this->actingAs($user)
        ->get(route('reports.users', [
            'date_from' => '2026-09-20',
            'date_to' => '2026-09-10',
        ]))
        ->assertSessionHasErrors('date_to');

    // Invalid status
    $this->actingAs($user)
        ->get(route('reports.users', ['status' => 'not-a-valid-status']))
        ->assertSessionHasErrors('status');

    // Invalid sort column
    $this->actingAs($user)
        ->get(route('reports.users', ['sort' => 'arbitrary_column']))
        ->assertSessionHasErrors('sort');

    // Invalid direction
    $this->actingAs($user)
        ->get(route('reports.users', ['direction' => 'sideways']))
        ->assertSessionHasErrors('direction');

    // Excessive per_page
    $this->actingAs($user)
        ->get(route('reports.users', ['per_page' => 999999]))
        ->assertSessionHasErrors('per_page');
});

test('user without reports.export cannot export user report', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsView->value);

    $this->actingAs($user)
        ->get(route('reports.users.export'))
        ->assertForbidden();
});

test('authorized user can export user report as CSV with formula injection protection', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    PermissionModel::findOrCreate(Permission::ReportsExport->value);
    $user->givePermissionTo(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsExport->value);

    // Create user with potential formula injection name
    User::factory()->create([
        'name' => '=HYPERLINK("http://attacker.com")',
        'email' => 'normal@example.com',
    ]);

    $response = $this->actingAs($user)
        ->get(route('reports.users.export'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    // Verify UTF-8 BOM
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();

    // Verify formula was escaped with a leading single quote
    expect($content)->toContain("'=HYPERLINK(\"\"http://attacker.com\"\")");
});

test('authorized user can export user report with custom date range, roles, and inactive users', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    PermissionModel::findOrCreate(Permission::ReportsExport->value);
    $user->givePermissionTo(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsExport->value);

    $role = Role::findOrCreate('Manager');
    $userWithRole = User::factory()->create([
        'is_active' => false,
    ]);
    $userWithRole->assignRole($role);

    $response = $this->actingAs($user)
        ->get(route('reports.users.export', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]));

    $response->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('Manager');
});

test('user report rejects date range exceeding 366 days and accepts exactly 366 days (PERF-001)', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsView->value);

    // 367 days apart -> fails
    $this->actingAs($user)
        ->get(route('reports.users', [
            'date_from' => '2025-01-01',
            'date_to' => '2026-01-03',
        ]))
        ->assertSessionHasErrors('date_to');

    // Exactly 365/366 days -> succeeds
    $this->actingAs($user)
        ->get(route('reports.users', [
            'date_from' => '2025-01-01',
            'date_to' => '2025-12-31',
        ]))
        ->assertSessionHasNoErrors();
});

test('export report validates filters with shared rules (VAL-001)', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    PermissionModel::findOrCreate(Permission::ReportsExport->value);
    $user->givePermissionTo(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsExport->value);

    $this->actingAs($user)
        ->get(route('reports.users.export', [
            'status' => 'bogus-status',
        ]))
        ->assertSessionHasErrors('status');

    $this->actingAs($user)
        ->get(route('reports.users.export', [
            'date_from' => '2024-01-01',
            'date_to' => '2026-01-01',
        ]))
        ->assertSessionHasErrors('date_to');
});

test('export report returns early when date fields have invalid formats', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    PermissionModel::findOrCreate(Permission::ReportsExport->value);
    $user->givePermissionTo(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsExport->value);

    $this->actingAs($user)
        ->get(route('reports.users.export', [
            'date_from' => 'invalid-date',
        ]))
        ->assertSessionHasErrors('date_from');
});

test('exports users without a per-row roles query', function (): void {
    $admin = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    PermissionModel::findOrCreate(Permission::ReportsExport->value);
    $admin->givePermissionTo(Permission::ReportsView->value);
    $admin->givePermissionTo(Permission::ReportsExport->value);

    $role = Role::findOrCreate('editor');
    User::factory()->count(25)->create()->each(fn (User $u): User => $u->assignRole($role));

    DB::enableQueryLog();

    $csv = $this->actingAs($admin)->get(route('reports.users.export'))->streamedContent();

    $roleQueries = collect(DB::getQueryLog())
        ->filter(fn (array $q): bool => str_contains($q['query'], 'model_has_roles'))
        ->count();

    expect($roleQueries)->toBeLessThanOrEqual(2)
        ->and(mb_substr_count($csv, "\n"))->toBeGreaterThanOrEqual(26);
});

test('report exports require view and export permissions', function (string $routeName, Permission $permission): void {
    $user = User::factory()->create();
    $user->givePermissionTo(PermissionModel::findOrCreate($permission->value));

    $this->actingAs($user)->get(route($routeName))->assertForbidden();
})->with([
    'user export without view' => ['reports.users.export', Permission::ReportsExport],
    'user export without export permission' => ['reports.users.export', Permission::ReportsView],
    'audit export without view' => ['reports.audit.export', Permission::ReportsExport],
    'audit export without export permission' => ['reports.audit.export', Permission::ReportsView],
]);
