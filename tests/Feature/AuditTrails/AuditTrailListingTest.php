<?php

declare(strict_types=1);

use App\Enums\AuditEvent;
use App\Enums\Permission;
use App\Models\Activity;
use App\Models\AuditTrail;
use App\Models\User;
use App\Queries\AuditTrails\AuditTrailListingQuery;
use Illuminate\Support\Facades\DB;
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

test('treats backslash in the search box as a literal character', function (): void {
    AuditTrail::factory()->create(['ip_address' => '10.0.0.1', 'auditable_id' => 'abc-123']);
    $literal = AuditTrail::factory()->create(['auditable_id' => '\\literal\\target']);

    $ids = collect(resolve(AuditTrailListingQuery::class)->handle(['search' => '\\'])->items())->pluck('id');

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

test('spatie activities can be retrieved and listed with inertia', function (): void {
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

test('spatie activities can be filtered by event type', function (): void {
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
            ->where('auditTrails.data.0.event_label', AuditEvent::UserCreated->label())
        );
});

test('spatie activities can be filtered by user id (causer id)', function (): void {
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
            ->where('auditTrails.data.0.user.id', $userA->id)
            ->where('auditTrails.data.0.user.email', $userA->email)
        );
});

test('spatie activities can be searched by ip address, subject id, or causer name', function (): void {
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

    $responseName = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['search' => 'Alice Auditor']));
    $responseName->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('auditTrails.data', 1));

    $responseIp = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['search' => '10.0.0.1']));
    $responseIp->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('auditTrails.data', 1));

    $responseId = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['search' => 'target-12345']));
    $responseId->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page->has('auditTrails.data', 1));
});

test('spatie activities search matches deleted user snapshot via properties actor name and email', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    $deletedActor = Activity::factory()->anonymous()->withActor('Former Auditor', 'former@example.com')->create();
    Activity::factory()->anonymous()->withActor('Other Auditor', 'other@example.com')->create();

    $responseName = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['search' => 'Former Auditor']));
    $responseName->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('auditTrails.data', 1)
            ->where('auditTrails.data.0.id', $deletedActor->id)
            ->where('auditTrails.data.0.actor_name', 'Former Auditor')
        );

    $responseEmail = $this->actingAs($admin)
        ->get(route('audit-trails.index', ['search' => 'former@example.com']));
    $responseEmail->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('auditTrails.data', 1)
            ->where('auditTrails.data.0.id', $deletedActor->id)
            ->where('auditTrails.data.0.actor_email', 'former@example.com')
        );
});

test('historical audit records and new spatie activities are both retrieved without duplication', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    // 1. Pure historical record (exists only in audit_trails)
    $historical = AuditTrail::factory()->create([
        'created_at' => now()->subHours(3),
    ]);

    // 2. Pure Spatie activity (exists only in activity_log)
    $spatie = Activity::factory()->create([
        'created_at' => now()->subHours(1),
    ]);

    // 3. Dual-recorded record (same ID in both audit_trails and activity_log)
    $dualId = (string) fake()->uuid();
    DB::table('activity_log')->insert([
        'id' => $dualId,
        'log_name' => 'default',
        'description' => 'Dual event',
        'event' => AuditEvent::SettingsUpdated->value,
        'created_at' => now()->subHours(2),
    ]);
    DB::table('audit_trails')->insert([
        'id' => $dualId,
        'event' => AuditEvent::SettingsUpdated->value,
        'created_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($admin)->get(route('audit-trails.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('audit-trails/index')
            ->has('auditTrails.data', 3)
            ->where('auditTrails.total', 3)
            ->where('auditTrails.data.0.id', $spatie->id)
            ->where('auditTrails.data.1.id', $dualId)
            ->where('auditTrails.data.2.id', $historical->id)
        );
});

test('pagination and sorting order across historical records and spatie activities', function (): void {
    $admin = User::factory()->create();
    $admin->givePermissionTo(PermissionModel::findOrCreate(Permission::AuditView->value));

    $records = [];
    for ($i = 1; $i <= 6; $i++) {
        if ($i % 2 === 0) {
            $records[] = Activity::factory()->create(['created_at' => now()->subHours($i)]);
        } else {
            $records[] = AuditTrail::factory()->create(['created_at' => now()->subHours($i)]);
        }
    }

    $response1 = $this->actingAs($admin)->get(route('audit-trails.index', ['per_page' => 3, 'page' => 1]));
    $response1->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('auditTrails.data', 3)
            ->where('auditTrails.total', 6)
            ->where('auditTrails.data.0.id', $records[0]->id)
            ->where('auditTrails.data.1.id', $records[1]->id)
            ->where('auditTrails.data.2.id', $records[2]->id)
        );

    $response2 = $this->actingAs($admin)->get(route('audit-trails.index', ['per_page' => 3, 'page' => 2]));
    $response2->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->has('auditTrails.data', 3)
            ->where('auditTrails.total', 6)
            ->where('auditTrails.data.0.id', $records[3]->id)
            ->where('auditTrails.data.1.id', $records[4]->id)
            ->where('auditTrails.data.2.id', $records[5]->id)
        );
});

test('event and user filters match both historical records and spatie activities', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    // Matching historical record
    $hMatch = AuditTrail::factory()->forUser($user)->forEvent(AuditEvent::UserCreated)->create();
    // Non-matching historical record
    AuditTrail::factory()->forUser($otherUser)->forEvent(AuditEvent::SettingsUpdated)->create();

    // Matching Spatie activity
    $sMatch = Activity::factory()->forUser($user)->forEvent(AuditEvent::UserCreated)->create();
    // Non-matching Spatie activity
    Activity::factory()->forUser($otherUser)->forEvent(AuditEvent::SettingsUpdated)->create();

    $query = resolve(AuditTrailListingQuery::class);

    // Filter by event
    $eventFiltered = $query->handle(['event' => AuditEvent::UserCreated->value]);
    expect($eventFiltered->total())->toBe(2)
        ->and(collect($eventFiltered->items())->pluck('id')->all())->toContain($hMatch->id, $sMatch->id);

    // Filter by user_id
    $userFiltered = $query->handle(['user_id' => $user->id]);
    expect($userFiltered->total())->toBe(2)
        ->and(collect($userFiltered->items())->pluck('id')->all())->toContain($hMatch->id, $sMatch->id);

    // Combined filter
    $combined = $query->handle(['event' => AuditEvent::UserCreated->value, 'user_id' => $user->id]);
    expect($combined->total())->toBe(2);
});

test('search filters across both historical records and spatie activities', function (): void {
    $historical = AuditTrail::factory()->create([
        'ip_address' => '10.99.1.5',
        'auditable_id' => 'target-historical-01',
    ]);

    $spatie = Activity::factory()->create([
        'ip_address' => '10.99.1.8',
        'subject_id' => 'target-spatie-02',
    ]);

    $query = resolve(AuditTrailListingQuery::class);

    $ipResults = $query->handle(['search' => '10.99.1.']);
    expect($ipResults->total())->toBe(2)
        ->and(collect($ipResults->items())->pluck('id')->all())->toContain($historical->id, $spatie->id);

    $targetHistorical = $query->handle(['search' => 'target-historical-01']);
    expect($targetHistorical->total())->toBe(1)
        ->and($targetHistorical->items()[0]->id)->toBe($historical->id);

    $targetSpatie = $query->handle(['search' => 'target-spatie-02']);
    expect($targetSpatie->total())->toBe(1)
        ->and($targetSpatie->items()[0]->id)->toBe($spatie->id);
});
