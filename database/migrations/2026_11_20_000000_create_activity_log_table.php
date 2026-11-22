<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('log_name')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->index(['subject_type', 'subject_id']);
            $table->string('event')->nullable()->index();
            $table->string('causer_type')->nullable();
            $table->foreignUuid('causer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['causer_type', 'causer_id']);
            $table->index(['event', 'created_at']);
            $table->index(['causer_id', 'created_at']);
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable()->index();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }
};
