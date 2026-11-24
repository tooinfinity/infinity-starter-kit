<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\AuditEvent;
use App\Models\Activity;
use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Activitylog\Contracts\Activity as ActivityContract;

test('activity model adheres to HasUuids contract and implements activity contract', function (): void {
    $traits = class_uses_recursive(Activity::class);

    expect(in_array(HasUuids::class, $traits, true))->toBeTrue()
        ->and(new Activity)->toBeInstanceOf(ActivityContract::class);
});

test('activity model generates a valid uuid primary key on creation', function (): void {
    $activity = Activity::factory()->create();

    expect($activity->id)->toBeString()
        ->and(Str::isUuid($activity->id))->toBeTrue();
});

test('activity model declares null updated_at and immutability handlers', function (): void {
    expect(Activity::UPDATED_AT)->toBeNull();

    $dispatcher = Activity::getEventDispatcher();
    expect($dispatcher)->not->toBeNull();

    $activity = new Activity;

    expect(fn () => $dispatcher->until('eloquent.updating: '.Activity::class, $activity))
        ->toThrow(RuntimeException::class, 'Audit trail records are immutable and cannot be updated.');

    expect(fn () => $dispatcher->until('eloquent.deleting: '.Activity::class, $activity))
        ->toThrow(RuntimeException::class, 'Audit trail records cannot be individually deleted. Use the retention prune command instead.');
});

test('activity model persists and casts native attributes', function (): void {
    $user = User::factory()->create();

    $activity = Activity::query()->create([
        'log_name' => 'users',
        'description' => 'User created',
        'event' => 'created',
        'causer_type' => $user->getMorphClass(),
        'causer_id' => $user->id,
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->id,
        'attribute_changes' => [
            'old' => ['name' => 'Old Name'],
            'attributes' => ['name' => 'New Name'],
        ],
        'properties' => [
            'actor' => [
                'name' => 'Alice Auditor',
                'email' => 'alice@example.com',
            ],
            'request' => [
                'url' => 'http://localhost/test',
            ],
            'tags' => ['users', 'auth'],
        ],
        'ip_address' => '127.0.0.1',
        'user_agent' => 'TestAgent/1.0',
    ]);

    expect($activity->id)->toBeString()
        ->and($activity->log_name)->toBe('users')
        ->and($activity->description)->toBe('User created')
        ->and($activity->event)->toBe('created')
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($user->id)
        ->and($activity->ip_address)->toBe('127.0.0.1')
        ->and($activity->user_agent)->toBe('TestAgent/1.0')
        ->and($activity->attribute_changes)->toBeInstanceOf(Collection::class)
        ->and($activity->attribute_changes?->get('old'))->toBe(['name' => 'Old Name'])
        ->and($activity->attribute_changes?->get('attributes'))->toBe(['name' => 'New Name'])
        ->and($activity->properties)->toBeInstanceOf(Collection::class)
        ->and($activity->getProperty('actor.name'))->toBe('Alice Auditor')
        ->and($activity->getProperty('actor.email'))->toBe('alice@example.com')
        ->and($activity->getProperty('tags'))->toBe(['users', 'auth']);
});

test('activity-to-causer polymorphic relationship resolves correctly with UUID-based model', function (): void {
    $user = User::factory()->create();

    $activity = Activity::factory()->forUser($user)->create();

    expect($activity->causer)->toBeInstanceOf(User::class)
        ->and($activity->causer?->id)->toBe($user->id)
        ->and($activity->causer_type)->toBe($user->getMorphClass())
        ->and($activity->causer_id)->toBe($user->id);

    $anonymous = Activity::factory()->anonymous()->create();

    expect($anonymous->causer)->toBeNull()
        ->and($anonymous->causer_type)->toBeNull()
        ->and($anonymous->causer_id)->toBeNull();
});

test('activity-to-causer polymorphic relationship supports non-user UUID-based causers', function (): void {
    Schema::create('custom_causers', function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('name');
        $table->timestamps();
    });

    $customCauserClass = new #[Unguarded] #[Table(name: 'custom_causers')] class extends Model
    {
        use HasFactory;
        use HasUuids;
    };

    $causer = $customCauserClass::query()->create(['name' => 'Automated Bot']);

    $activity = Activity::query()->create([
        'causer_type' => $causer->getMorphClass(),
        'causer_id' => $causer->id,
        'description' => 'Action by non-user causer',
    ]);

    expect($activity->causer_type)->toBe($causer->getMorphClass())
        ->and($activity->causer_id)->toBe($causer->id)
        ->and($activity->causer)->toBeInstanceOf(Model::class)
        ->and($activity->causer?->getKey())->toBe($causer->id);
});

test('activity-to-subject polymorphic relationship resolves correctly with UUID-based model and string IDs', function (): void {
    $user = User::factory()->create();

    $activityWithUserSubject = Activity::query()->create([
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->id,
        'description' => 'User subject',
    ]);

    expect($activityWithUserSubject->subject)->toBeInstanceOf(User::class)
        ->and($activityWithUserSubject->subject?->id)->toBe($user->id);

    $arbitrarySubjectId = 'custom-string-key-123';
    $activityWithStringSubject = Activity::query()->create([
        'subject_type' => 'App\\Models\\ExternalResource',
        'subject_id' => $arbitrarySubjectId,
        'description' => 'Arbitrary string subject',
    ]);

    expect($activityWithStringSubject->subject_id)->toBe($arbitrarySubjectId)
        ->and($activityWithStringSubject->subject_type)->toBe('App\\Models\\ExternalResource');
});

test('package configuration resolves custom activity model and activity helper uses it', function (): void {
    expect(config('activitylog.activity_model'))->toBe(Activity::class);

    $user = User::factory()->create();

    $activity = activity()
        ->causedBy($user)
        ->performedOn($user)
        ->event('custom_event')
        ->log('Custom activity logged via Spatie helper');

    expect($activity)->toBeInstanceOf(Activity::class)
        ->and($activity->id)->toBeString()
        ->and(Str::isUuid($activity->id))->toBeTrue()
        ->and($activity->causer_id)->toBe($user->id)
        ->and($activity->subject_id)->toBe($user->id)
        ->and($activity->event)->toBe('custom_event')
        ->and($activity->description)->toBe('Custom activity logged via Spatie helper');
});

test('activity model supports spatie query scopes', function (): void {
    $user = User::factory()->create();

    $activity = Activity::query()->create([
        'log_name' => 'settings',
        'event' => 'updated',
        'description' => 'Settings updated',
        'causer_type' => $user->getMorphClass(),
        'causer_id' => $user->id,
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->id,
    ]);

    expect(Activity::query()->inLog('settings')->where('id', $activity->id)->exists())->toBeTrue()
        ->and(Activity::query()->causedBy($user)->where('id', $activity->id)->exists())->toBeTrue()
        ->and(Activity::query()->forSubject($user)->where('id', $activity->id)->exists())->toBeTrue()
        ->and(Activity::query()->forEvent('updated')->where('id', $activity->id)->exists())->toBeTrue();
});

test('activity model provides compatibility accessors for presentation and export layers', function (): void {
    $user = User::factory()->create(['name' => 'Bob Builder', 'email' => 'bob@example.com']);

    $activity = Activity::query()->create([
        'event' => AuditEvent::UserUpdated->value,
        'description' => 'User updated',
        'causer_type' => $user->getMorphClass(),
        'causer_id' => $user->id,
        'subject_type' => $user->getMorphClass(),
        'subject_id' => $user->id,
        'attribute_changes' => [
            'old' => ['name' => 'Old Bob'],
            'attributes' => ['name' => 'New Bob'],
        ],
        'properties' => [
            'actor' => [
                'name' => 'Bob Builder',
                'email' => 'bob@example.com',
            ],
            'request' => [
                'url' => 'http://localhost/users/update',
            ],
            'tags' => ['users', 'profile'],
        ],
        'ip_address' => '192.168.1.50',
        'user_agent' => 'TestBrowser/1.0',
    ]);

    expect($activity->user)->toBeInstanceOf(User::class)
        ->and($activity->user?->id)->toBe($user->id)
        ->and($activity->user_id)->toBe($user->id)
        ->and($activity->actor_name)->toBe('Bob Builder')
        ->and($activity->actor_email)->toBe('bob@example.com')
        ->and($activity->url)->toBe('http://localhost/users/update')
        ->and($activity->tags)->toBe(['users', 'profile'])
        ->and($activity->old_values)->toBe(['name' => 'Old Bob'])
        ->and($activity->new_values)->toBe(['name' => 'New Bob'])
        ->and($activity->auditable_type)->toBe($user->getMorphClass())
        ->and($activity->auditable_id)->toBe($user->id)
        ->and($activity->event_label)->toBe(AuditEvent::UserUpdated->label())
        ->and($activity->audit_event)->toBe(AuditEvent::UserUpdated);
});

test('activity withHistorical combines activity_log and audit_trails', function (): void {
    $user = User::factory()->create();

    $legacy = AuditTrail::factory()->create([
        'user_id' => $user->id,
        'actor_name' => $user->name,
        'actor_email' => $user->email,
        'event' => AuditEvent::UserCreated,
    ]);

    $spatie = Activity::factory()->forUser($user)->create([
        'event' => AuditEvent::UserUpdated->value,
    ]);

    $results = Activity::withHistorical()->get();

    expect($results->pluck('id')->all())->toContain($legacy->id, $spatie->id);

    $legacyRecord = $results->firstWhere('id', $legacy->id);
    expect($legacyRecord)->not->toBeNull()
        ->and($legacyRecord?->event)->toBe(AuditEvent::UserCreated->value)
        ->and($legacyRecord?->actor_name)->toBe($user->name)
        ->and($legacyRecord?->actor_email)->toBe($user->email);
});

test('activity log migration executes cleanly and establishes expected schema and indexes', function (): void {
    Schema::dropIfExists('activity_log');
    expect(Schema::hasTable('activity_log'))->toBeFalse();

    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_11_20_000000_create_activity_log_table.php');
    $migration->up();

    expect(Schema::hasTable('activity_log'))->toBeTrue()
        ->and(Schema::hasColumns('activity_log', [
            'id',
            'log_name',
            'description',
            'subject_type',
            'subject_id',
            'event',
            'causer_type',
            'causer_id',
            'attribute_changes',
            'properties',
            'ip_address',
            'user_agent',
            'created_at',
        ]))->toBeTrue();

    $indexes = collect(Schema::getIndexes('activity_log'))->pluck('columns')->all();
    expect($indexes)->toContain(['id'])
        ->and($indexes)->toContain(['subject_type', 'subject_id'])
        ->and($indexes)->toContain(['causer_type', 'causer_id'])
        ->and($indexes)->toContain(['event', 'created_at'])
        ->and($indexes)->toContain(['causer_id', 'created_at']);
});

test('actorName and actorEmail accessors prefer recorded snapshot properties over live causer', function (): void {
    $user = User::factory()->create([
        'name' => 'Alice Original',
        'email' => 'alice-original@example.com',
    ]);

    $activity = Activity::query()->create([
        'causer_type' => User::class,
        'causer_id' => $user->id,
        'properties' => [
            'actor' => [
                'name' => 'Alice Snapshot',
                'email' => 'alice-snapshot@example.com',
            ],
        ],
    ]);

    // Rename live causer user
    $user->update([
        'name' => 'Alice Renamed',
        'email' => 'alice-renamed@example.com',
    ]);

    $freshActivity = Activity::query()->with('causer')->findOrFail($activity->id);

    expect($freshActivity->actor_name)->toBe('Alice Snapshot')
        ->and($freshActivity->actor_email)->toBe('alice-snapshot@example.com');
});

test('withHistorical query falls back cleanly when audit_trails table does not exist', function (): void {
    Schema::dropIfExists('audit_trails');

    Activity::query()->create([
        'description' => 'Test activity',
    ]);

    $results = Activity::withHistorical()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->description)->toBe('Test activity');

    // Restore table for subsequent tests
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_09_20_000000_create_audit_trails_table.php');
    $migration->up();
    /** @var Migration $actorFieldsMigration */
    $actorFieldsMigration = require database_path('migrations/2026_10_20_234750_add_actor_fields_to_audit_trails_table.php');
    $actorFieldsMigration->up();
});

test('actorName and actorEmail accessors fall back to live causer when no snapshot exists', function (): void {
    $user = User::factory()->create([
        'name' => 'Bob User',
        'email' => 'bob@example.com',
    ]);

    $activity = Activity::query()->create([
        'causer_type' => User::class,
        'causer_id' => $user->id,
        'properties' => null,
    ]);

    $freshActivity = Activity::query()->with('causer')->findOrFail($activity->id);

    expect($freshActivity->actor_name)->toBe('Bob User')
        ->and($freshActivity->actor_email)->toBe('bob@example.com');
});

test('withHistorical query correctly decodes legacy old_values and new_values from historical audit records', function (): void {
    $audit = AuditTrail::factory()->create([
        'old_values' => ['name' => 'Old Name'],
        'new_values' => ['name' => 'New Name'],
    ]);

    $record = Activity::withHistorical()->where('id', $audit->id)->firstOrFail();

    expect($record->old_values)->toBe(['name' => 'Old Name'])
        ->and($record->new_values)->toBe(['name' => 'New Name']);
});
