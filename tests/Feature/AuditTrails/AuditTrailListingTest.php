<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\User;
use App\Queries\AuditTrails\AuditTrailListingQuery;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission as PermissionModel;

test('authorized user can view audit trails listing page with inertia', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    Activity::factory()->count(3)->create();

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

    Activity::factory()->forEvent(AuditEvent::UserCreated)->create();
    Activity::factory()->forEvent(AuditEvent::UserUpdated)->create();
    Activity::factory()->forEvent(AuditEvent::SettingsUpdated)->create();

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

    Activity::factory()->forUser($userA)->create();
    Activity::factory()->forUser($userB)->create();

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

    Activity::factory()->create(['created_at' => now()->subDays(5)]);
    Activity::factory()->create(['created_at' => now()->subDays(2)]);
    Activity::factory()->create(['created_at' => now()]);

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
    Activity::factory()->forUser($specificUser)->create([
        'ip_address' => '10.0.0.1',
        'subject_id' => 'target-12345',
    ]);
    Activity::factory()->create([
        'ip_address' => '192.168.1.1',
        'subject_id' => 'other-99999',
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
    Activity::factory()->create(['ip_address' => '10.0.0.1']);
    $literal = Activity::factory()->create(['subject_id' => '%literal-target']);

    $ids = collect(resolve(AuditTrailListingQuery::class)->handle(['search' => '%'])->items())->pluck('id');

    expect($ids->all())->toBe([$literal->id]);
});

test('treats _ in the search box as a literal character', function (): void {
    Activity::factory()->create(['ip_address' => '10.0.0.1', 'subject_id' => 'abc-123']);
    $literal = Activity::factory()->create(['subject_id' => '_literal_target']);

    $ids = collect(resolve(AuditTrailListingQuery::class)->handle(['search' => '_'])->items())->pluck('id');

    expect($ids->all())->toBe([$literal->id]);
});

test('treats backslash in the search box as a literal character', function (): void {
    Activity::factory()->create(['ip_address' => '10.0.0.1', 'subject_id' => 'abc-123']);
    $literal = Activity::factory()->create(['subject_id' => '\\literal\\target']);

    $ids = collect(resolve(AuditTrailListingQuery::class)->handle(['search' => '\\'])->items())->pluck('id');

    expect($ids->all())->toBe([$literal->id]);
});

test('search matches deleted user via actor_name and actor_email', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    $deletedActor = Activity::factory()->anonymous()->withActor('Former Admin', 'former@example.com')->create();
    Activity::factory()->anonymous()->withActor('Other Person', 'other@example.com')->create();

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

test('finds audit record by literal underscore in actor email when no related user or subject matches', function (): void {
    Activity::factory()->anonymous()->withActor(null, 'johnXdoe@example.com')->create([
        'subject_type' => null,
        'subject_id' => 'target-other',
        'ip_address' => '10.0.0.1',
    ]);

    $matched = Activity::factory()->anonymous()->withActor(null, 'john_doe@example.com')->create([
        'subject_type' => null,
        'subject_id' => 'target-none',
        'ip_address' => '10.0.0.2',
    ]);

    $ids = collect(resolve(AuditTrailListingQuery::class)->handle(['search' => 'john_doe'])->items())->pluck('id');

    expect($ids->all())->toBe([$matched->id]);
});

test('audit trails pagination per_page works correctly', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    Activity::factory()->count(10)->create();

    $response = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['per_page' => 4]));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('audit-trails/index')
            ->has('auditTrails.data', 4)
            ->where('auditTrails.total', 10)
        );
});

test('jsonLikeEscapeSql formats correctly for all database drivers and throws on unsupported path', function (): void {
    foreach (['actor.name', 'actor.email'] as $path) {
        expect(AuditTrailListingQuery::jsonLikeEscapeSql($path, 'sqlite'))->toContain('json_extract')
            ->and(AuditTrailListingQuery::jsonLikeEscapeSql($path, 'pgsql'))->toContain('->>')
            ->and(AuditTrailListingQuery::jsonLikeEscapeSql($path, 'sqlsrv'))->toContain('JSON_VALUE')
            ->and(AuditTrailListingQuery::jsonLikeEscapeSql($path, 'mysql'))->toContain('json_unquote');
    }

    expect(fn (): string => AuditTrailListingQuery::jsonLikeEscapeSql('invalid.path'))
        ->toThrow(InvalidArgumentException::class, 'Unsupported JSON path: invalid.path');
});

test('audit trails listing formats lifecycle events and empty events into labels', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    $expectedLabels = [
        Activity::factory()->create(['event' => 'created', 'description' => 'created'])->id => 'User Created',
        Activity::factory()->create(['event' => 'updated', 'description' => 'updated'])->id => 'User Updated',
        Activity::factory()->create(['event' => 'deleted', 'description' => 'deleted'])->id => 'User Deleted',
        Activity::factory()->create(['event' => null, 'description' => 'unknown'])->id => '',
        Activity::factory()->create(['event' => 'custom_action', 'description' => 'custom_action'])->id => 'Custom Action',
    ];

    $response = $this->actingAs($admin)
        ->get(route('audit-trails.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('audit-trails/index')
            ->has('auditTrails.data', 5)
            ->where('auditTrails.data', fn ($rows): bool => collect($rows)->pluck('event_label', 'id')->sortKeys()->all()
                === collect($expectedLabels)->sortKeys()->all())
        );
});
