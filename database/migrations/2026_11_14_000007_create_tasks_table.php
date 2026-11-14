<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            return;
        }

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('deal_id')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 255);
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('pending');
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement(
            'CREATE INDEX idx_tasks_assigned_due ON tasks (assigned_to, due_at) WHERE status IN (\'pending\', \'in_progress\')'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_tasks_assigned_due');
        Schema::dropIfExists('tasks');
    }
};
