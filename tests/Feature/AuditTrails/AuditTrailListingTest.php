<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Models\AuditTrail;
use App\Models\User;
use App\Queries\AuditTrails\AuditTrailListingQuery;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as PermissionModel;

test('authorized user can view audit trails listing page with inertia', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    AuditTrail::factory()->count(3)->create();

    $response = $this->actingAs($admin)
        ->get(route('audit-trails.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('audit-trails/index')
            ->has('auditTrails.data', 3)
            ->has('filters')
            ->has('availableEvents')
        );
});

test('audit trails can be filtered by event type', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    AuditTrail::factory()->forEvent(AuditEvent::UserCreated)->create();
    AuditTrail::factory()->forEvent(AuditEvent::UserUpdated)->create();
    AuditTrail::factory()->forEvent(AuditEvent::SettingsUpdated)->create();

    $response = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['event' => AuditEvent::UserCreated->value]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('audit-trails/index')
            ->has('auditTrails.data', 1)
            ->where('auditTrails.data.0.event', AuditEvent::UserCreated->value)
        );
});

test('audit trails can be filtered by user id', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    $userA = User::factory()->create();
    $userB = User::factory()->create();

    AuditTrail::factory()->create(['user_id' => $userA->id]);
    AuditTrail::factory()->create(['user_id' => $userB->id]);

    $response = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['user_id' => $userA->id]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('audit-trails/index')
            ->has('auditTrails.data', 1)
            ->where('auditTrails.data.0.user_id', $userA->id)
        );
});

test('audit trails can be filtered by date range', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    AuditTrail::factory()->create(['created_at' => now()->subDays(5)]);
    AuditTrail::factory()->create(['created_at' => now()->subDays(2)]);
    AuditTrail::factory()->create(['created_at' => now()]);

    $response = $this->actingAs($admin)
        ->get(route('audit-trails.index', [
            'date_from' => now()->subDays(3)->toDateString(),
            'date_to' => now()->subDay()->toDateString(),
        ]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('audit-trails/index')
            ->has('auditTrails.data', 1)
        );
});

test('audit trails can be searched by ip address, auditable id, or user name', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    $specificUser = User::factory()->create(['name' => 'Alice Auditor']);
    AuditTrail::factory()->create([
        'user_id' => $specificUser->id,
        'ip_address' => '10.0.0.1',
        'auditable_id' => 'target-12345',
    ]);
    AuditTrail::factory()->create([
        'ip_address' => '192.168.1.1',
        'auditable_id' => 'other-99999',
    ]);

    // Search by user name
    $responseName = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['search' => 'Alice Auditor']));
    $responseName->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('auditTrails.data', 1));

    // Search by IP prefix
    $responseIp = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['search' => '10.0.0.1']));
    $responseIp->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('auditTrails.data', 1));

    // Search by Auditable ID prefix
    $responseId = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['search' => 'target-12345']));
    $responseId->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('auditTrails.data', 1));
});

test('treats % in the search box as a literal character', function (): void {
    AuditTrail::factory()->create(['ip_address' => '10.0.0.1']);
    $literal = AuditTrail::factory()->create(['auditable_id' => '%literal-target']);

    $ids = collect(resolve(AuditTrailListingQuery::class)->handle(['search' => '%'])->items())->pluck('id');

    expect($ids->all())->toBe([$literal->id]);
});

test('treats _ in the search box as a literal character', function (): void {
    AuditTrail::factory()->create(['ip_address' => '10.0.0.1', 'auditable_id' => 'abc-123']);
    $literal = AuditTrail::factory()->create(['auditable_id' => '_literal_target']);

    $ids = collect(resolve(AuditTrailListingQuery::class)->handle(['search' => '_'])->items())->pluck('id');

    expect($ids->all())->toBe([$literal->id]);
});

test('search matches deleted user via actor_name and actor_email', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    $deletedActor = AuditTrail::factory()->create([
        'user_id' => null,
        'actor_name' => 'Former Admin',
        'actor_email' => 'former@example.com',
    ]);
    AuditTrail::factory()->create([
        'user_id' => null,
        'actor_name' => 'Other Person',
        'actor_email' => 'other@example.com',
    ]);

    $responseName = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['search' => 'Former Admin']));
    $responseName->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('auditTrails.data', 1)
            ->where('auditTrails.data.0.id', $deletedActor->id)
        );

    $responseEmail = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['search' => 'former@example.com']));
    $responseEmail->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('auditTrails.data', 1)
            ->where('auditTrails.data.0.id', $deletedActor->id)
        );
});

test('audit trails pagination per_page works correctly', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    AuditTrail::factory()->count(10)->create();

    $response = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['per_page' => 4]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('audit-trails/index')
            ->has('auditTrails.data', 4)
            ->where('auditTrails.total', 10)
        );
});
