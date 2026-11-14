<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE audit_logs ALTER COLUMN subject_id TYPE varchar(36) USING subject_id::varchar');
    }

    public function down(): void
    {
        // ponytail: widening to uuid is intentionally irreversible; bigint cannot hold UUID subject ids.
    }
};
