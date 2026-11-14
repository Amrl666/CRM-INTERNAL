<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_sources', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->text('description')->nullable();
            $table->timestampsTz();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('domain')->nullable()->index();
            $table->text('website')->nullable();
            $table->string('industry', 100)->nullable();
            $table->string('employee_range', 50)->nullable();
            $table->string('city', 100)->nullable();
            $table->char('country_code', 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('job_title', 150)->nullable();
            $table->string('email', 320)->nullable();
            $table->string('normalized_email', 320)->nullable()->index();
            $table->string('phone_e164', 20)->nullable()->index();
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();
        });

        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('source_id')->nullable()->constrained('lead_sources')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('NEW');
            $table->smallInteger('qualification_score')->default(0);
            $table->string('priority', 20)->default('medium');
            $table->timestampTz('qualified_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->index('status');
            $table->index(['owner_id', 'status']);
            $table->index('qualification_score');
        });

        DB::statement('ALTER TABLE leads ADD CONSTRAINT leads_score_range CHECK (qualification_score BETWEEN 0 AND 100)');

        Schema::create('lead_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at');
            $table->timestampTz('unassigned_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 50)->unique();
            $table->string('color', 20)->nullable();
            $table->timestampsTz();
        });

        Schema::create('lead_tags', function (Blueprint $table) {
            $table->foreignUuid('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tag_id')->constrained()->cascadeOnDelete();
            $table->primary(['lead_id', 'tag_id']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lead_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('deal_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50);
            $table->string('subject')->nullable();
            $table->text('description')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('lead_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('deal_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestampsTz();
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('attachable_type', 100);
            $table->uuid('attachable_id');
            $table->string('disk', 50)->default('local');
            $table->string('path');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['attachable_type', 'attachable_id']);
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('file_hash', 64)->unique();
            $table->string('filename');
            $table->string('status', 30)->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('import_rows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->jsonb('raw')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error')->nullable();
            $table->foreignUuid('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampsTz();
            $table->index(['batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_rows');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('lead_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('lead_assignments');
        Schema::dropIfExists('leads');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('lead_sources');
    }
};
