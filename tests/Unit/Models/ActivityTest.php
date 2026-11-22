<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Activity;
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
