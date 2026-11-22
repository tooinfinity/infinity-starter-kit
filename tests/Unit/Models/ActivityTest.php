<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Database\Schema\Grammars\PostgresGrammar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PDO;
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

test('activity_log table has required columns and indexes in database schema', function (): void {
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
});

test('activity log table blueprint compiles valid DDL for sqlite, mysql, and pgsql', function (): void {
    $buildBlueprint = function (Connection $connection): Blueprint {
        $blueprint = new Blueprint($connection, 'activity_log');
        $blueprint->create();
        $blueprint->uuid('id')->primary();
        $blueprint->string('log_name')->nullable()->index();
        $blueprint->text('description')->nullable();
        $blueprint->string('subject_type')->nullable();
        $blueprint->string('subject_id')->nullable();
        $blueprint->index(['subject_type', 'subject_id']);
        $blueprint->string('event')->nullable()->index();
        $blueprint->string('causer_type')->nullable();
        $blueprint->string('causer_id')->nullable();
        $blueprint->index(['causer_type', 'causer_id']);
        $blueprint->index(['event', 'created_at']);
        $blueprint->index(['causer_id', 'created_at']);
        $blueprint->json('attribute_changes')->nullable();
        $blueprint->json('properties')->nullable();
        $blueprint->string('ip_address', 45)->nullable()->index();
        $blueprint->text('user_agent')->nullable();
        $blueprint->timestamp('created_at')->nullable()->index();

        return $blueprint;
    };

    /** @var Connection $sqliteConnection */
    $sqliteConnection = DB::connection();
    $sqliteConnection->useDefaultSchemaGrammar();

    $sqliteSql = implode(';', $buildBlueprint($sqliteConnection)->toSql());
    expect($sqliteSql)->toContain('create table "activity_log"')
        ->and($sqliteSql)->toContain('"id" varchar not null')
        ->and($sqliteSql)->toContain('primary key ("id")')
        ->and($sqliteSql)->toContain('"causer_type" varchar')
        ->and($sqliteSql)->toContain('"causer_id" varchar')
        ->and($sqliteSql)->toContain('"subject_type" varchar')
        ->and($sqliteSql)->toContain('"subject_id" varchar');

    $mysqlGrammar = new MySqlGrammar($sqliteConnection);
    $mysqlConnection = new MySqlConnection(
        fn (): PDO => new PDO('sqlite::memory:'),
        'laravel',
        '',
        ['charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci']
    );
    $mysqlConnection->setSchemaGrammar($mysqlGrammar);

    $mysqlSql = implode(';', $buildBlueprint($mysqlConnection)->toSql());
    expect($mysqlSql)->toContain('create table `activity_log`')
        ->and($mysqlSql)->toContain('`id` char(36) not null')
        ->and($mysqlSql)->toContain('primary key (`id`)')
        ->and($mysqlSql)->toContain('`causer_type` varchar(255)')
        ->and($mysqlSql)->toContain('`causer_id` varchar(255)')
        ->and($mysqlSql)->toContain('`subject_type` varchar(255)')
        ->and($mysqlSql)->toContain('`subject_id` varchar(255)')
        ->and($mysqlSql)->toContain('`attribute_changes` json')
        ->and($mysqlSql)->toContain('`properties` json');

    $pgsqlGrammar = new PostgresGrammar($sqliteConnection);
    $pgsqlConnection = new PostgresConnection(
        fn (): PDO => new PDO('sqlite::memory:'),
        'laravel',
        '',
        ['charset' => 'utf8']
    );
    $pgsqlConnection->setSchemaGrammar($pgsqlGrammar);

    $pgsqlSql = implode(';', $buildBlueprint($pgsqlConnection)->toSql());
    expect($pgsqlSql)->toContain('create table "activity_log"')
        ->and($pgsqlSql)->toContain('"id" uuid not null')
        ->and($pgsqlSql)->toContain('primary key ("id")')
        ->and($pgsqlSql)->toContain('"causer_type" varchar(255)')
        ->and($pgsqlSql)->toContain('"causer_id" varchar(255)')
        ->and($pgsqlSql)->toContain('"subject_type" varchar(255)')
        ->and($pgsqlSql)->toContain('"subject_id" varchar(255)')
        ->and($pgsqlSql)->toContain('"attribute_changes" json')
        ->and($pgsqlSql)->toContain('"properties" json');
});
