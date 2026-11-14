<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contact_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20);
            $table->string('source', 100)->nullable();
            $table->text('evidence')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('recorded_at');
            $table->timestampTz('created_at')->nullable();

            $table->index(['contact_id', 'channel', 'recorded_at']);
        });

        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION prevent_consent_mutation() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'consent_records bersifat append-only';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER consent_records_append_only
                BEFORE UPDATE OR DELETE ON consent_records
                FOR EACH ROW EXECUTE FUNCTION prevent_consent_mutation();
        SQL);

        Schema::create('suppression_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('channel', 20);
            $table->string('normalized_address', 320);
            $table->string('reason', 200)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['channel', 'normalized_address']);
        });
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS consent_records_append_only ON consent_records');
        DB::unprepared('DROP FUNCTION IF EXISTS prevent_consent_mutation()');
        Schema::dropIfExists('suppression_entries');
        Schema::dropIfExists('consent_records');
    }
};
