<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('audit_trails') && Schema::hasTable('activity_log')) {
            DB::table('audit_trails')->oldest()->chunk(100, function ($rows): void {
                $payloads = [];

                foreach ($rows as $row) {
                    $oldValues = is_string($row->old_values) && $row->old_values !== '' ? json_decode($row->old_values, true) : null;
                    $newValues = is_string($row->new_values) && $row->new_values !== '' ? json_decode($row->new_values, true) : null;
                    $tags = is_string($row->tags) && $row->tags !== '' ? json_decode($row->tags, true) : [];

                    $attributeChanges = null;
                    if ($oldValues !== null || $newValues !== null) {
                        $attributeChanges = json_encode([
                            'attributes' => $newValues ?? [],
                            'old' => $oldValues ?? [],
                        ]);
                    }

                    $properties = json_encode([
                        'actor' => [
                            'name' => $row->actor_name,
                            'email' => $row->actor_email,
                        ],
                        'request' => [
                            'url' => $row->url,
                            'ip_address' => $row->ip_address,
                            'user_agent' => $row->user_agent,
                        ],
                        'tags' => $tags ?? [],
                    ]);

                    $payloads[] = [
                        'id' => $row->id,
                        'log_name' => 'default',
                        'description' => $row->event,
                        'subject_type' => $row->auditable_type,
                        'subject_id' => $row->auditable_id,
                        'causer_type' => $row->user_id ? User::class : null,
                        'causer_id' => $row->user_id,
                        'event' => $row->event,
                        'attribute_changes' => $attributeChanges,
                        'properties' => $properties,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->created_at,
                    ];
                }

                if ($payloads !== []) {
                    DB::table('activity_log')->insert($payloads);
                }
            });

            Schema::dropIfExists('audit_trails');
        }
    }
};
