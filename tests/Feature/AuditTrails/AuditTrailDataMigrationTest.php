<?php

declare(strict_types=1);

use App\Models\AuditTrail;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

test('data migration transfers legacy audit_trails records to activity_log seamlessly', function (): void {
    if (! Schema::hasTable('audit_trails')) {
        Schema::create('audit_trails', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('event')->index();
            $table->string('auditable_type')->nullable();
            $table->string('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('tags')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    $user = User::factory()->create();
    $id = (string) Str::uuid();

    DB::table('audit_trails')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'actor_name' => 'Legacy Admin',
        'actor_email' => 'legacy@example.com',
        'event' => 'user_updated',
        'auditable_type' => User::class,
        'auditable_id' => $user->id,
        'old_values' => json_encode(['name' => 'Old Name']),
        'new_values' => json_encode(['name' => 'New Name']),
        'url' => 'http://localhost/users/'.$user->id,
        'ip_address' => '10.0.0.1',
        'user_agent' => 'LegacyBrowser/1.0',
        'tags' => json_encode(['upgrade', 'migration']),
        'created_at' => now()->subDays(10)->toDateTimeString(),
    ]);

    $migration = require database_path('migrations/2026_11_20_000001_migrate_audit_trails_to_activity_log.php');
    $migration->up();

    expect(Schema::hasTable('audit_trails'))->toBeFalse();

    $migrated = AuditTrail::query()->find($id);
    expect($migrated)->not->toBeNull()
        ->and($migrated->id)->toBe($id)
        ->and($migrated->user_id)->toBe($user->id)
        ->and($migrated->actor_name)->toBe('Legacy Admin')
        ->and($migrated->actor_email)->toBe('legacy@example.com')
        ->and($migrated->event)->toBe('user_updated')
        ->and($migrated->auditable_type)->toBe(User::class)
        ->and($migrated->auditable_id)->toBe($user->id)
        ->and($migrated->old_values)->toBe(['name' => 'Old Name'])
        ->and($migrated->new_values)->toBe(['name' => 'New Name'])
        ->and($migrated->url)->toBe('http://localhost/users/'.$user->id)
        ->and($migrated->ip_address)->toBe('10.0.0.1')
        ->and($migrated->user_agent)->toBe('LegacyBrowser/1.0')
        ->and($migrated->tags)->toBe(['upgrade', 'migration']);
});
