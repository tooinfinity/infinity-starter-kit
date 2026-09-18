<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/* @chisel-notifications */

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('notification_type');
            $table->boolean('database_enabled')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'notification_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};

/* @end-chisel-notifications */
