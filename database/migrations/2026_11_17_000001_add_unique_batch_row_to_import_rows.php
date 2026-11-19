<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->unique(
                ['batch_id', 'row_number'],
                'import_rows_batch_row_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropUnique('import_rows_batch_row_unique');
        });
    }
};
