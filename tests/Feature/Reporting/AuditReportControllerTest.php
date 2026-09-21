<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Models\AuditTrail;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as PermissionModel;

/* @chisel-reporting */

test('unauthenticated user cannot access audit report', function (): void {
    $this->get(route('reports.audit'))
        ->assertRedirect(route('login'));
});

test('user without reports.view cannot access audit report', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('reports.audit'))
        ->assertForbidden();
});

test('authorized user can view audit report with valid props', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsView->value);

    AuditTrail::factory()->create([
        'user_id' => $user->id,
        'event' => AuditEvent::UserCreated,
    ]);

    $this->actingAs($user)
        ->get(route('reports.audit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('reports/audit')
            ->has('summary')
            ->has('series')
            ->has('breakdown')
            ->has('auditTrails')
            ->has('availableEvents')
            ->has('filters')
        );
});

test('audit report validates filter parameters', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsView->value);

    // Invalid date_from
    $this->actingAs($user)
        ->get(route('reports.audit', ['date_from' => 'invalid-date']))
        ->assertSessionHasErrors('date_from');

    // date_to before date_from
    $this->actingAs($user)
        ->get(route('reports.audit', [
            'date_from' => '2026-09-20',
            'date_to' => '2026-09-10',
        ]))
        ->assertSessionHasErrors('date_to');

    // Invalid user_id
    $this->actingAs($user)
        ->get(route('reports.audit', ['user_id' => 'not-a-valid-uuid']))
        ->assertSessionHasErrors('user_id');

    // Invalid event length (> 50)
    $this->actingAs($user)
        ->get(route('reports.audit', ['event' => str_repeat('a', 51)]))
        ->assertSessionHasErrors('event');

    // Invalid sort column
    $this->actingAs($user)
        ->get(route('reports.audit', ['sort' => 'arbitrary_column']))
        ->assertSessionHasErrors('sort');

    // Invalid direction
    $this->actingAs($user)
        ->get(route('reports.audit', ['direction' => 'diagonal']))
        ->assertSessionHasErrors('direction');

    // Excessive per_page
    $this->actingAs($user)
        ->get(route('reports.audit', ['per_page' => 500]))
        ->assertSessionHasErrors('per_page');
});

test('user without reports.export cannot export audit report', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsView->value);

    $this->actingAs($user)
        ->get(route('reports.audit.export'))
        ->assertForbidden();
});

test('authorized user can export audit report as CSV with formula injection protection', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    PermissionModel::findOrCreate(Permission::ReportsExport->value);
    $user->givePermissionTo(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsExport->value);

    AuditTrail::factory()->create([
        'user_id' => $user->id,
        'event' => AuditEvent::UserCreated,
        'ip_address' => '=SUM(1+1)',
    ]);

    $response = $this->actingAs($user)
        ->get(route('reports.audit.export'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $content = $response->streamedContent();

    // Verify UTF-8 BOM
    expect(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue();

    // Verify formula was escaped with a leading single quote
    expect($content)->toContain("'=SUM(1+1)");
});

test('authorized user can export audit report with custom date range and system actor', function (): void {
    $user = User::factory()->create();
    PermissionModel::findOrCreate(Permission::ReportsView->value);
    PermissionModel::findOrCreate(Permission::ReportsExport->value);
    $user->givePermissionTo(Permission::ReportsView->value);
    $user->givePermissionTo(Permission::ReportsExport->value);

    AuditTrail::factory()->create([
        'user_id' => null,
        'event' => AuditEvent::SettingsUpdated,
        'ip_address' => '127.0.0.1',
    ]);

    $response = $this->actingAs($user)
        ->get(route('reports.audit.export', [
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]));

    $response->assertOk();

    $content = $response->streamedContent();
    expect($content)->toContain('settings.updated');
});

/* @end-chisel-reporting */
