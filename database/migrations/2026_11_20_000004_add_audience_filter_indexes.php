<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->index('industry', 'companies_industry_idx');
            $table->index('city', 'companies_city_idx');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->index('source_id', 'leads_source_id_idx');
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->index('lead_id', 'import_rows_lead_id_idx');
            $table->index(['batch_id', 'lead_id'], 'import_rows_batch_lead_idx');
        });

        Schema::table('lead_tags', function (Blueprint $table) {
            $table->index(['tag_id', 'lead_id'], 'lead_tags_tag_lead_idx');
        });
    }

    public function down(): void
    {
        Schema::table('lead_tags', function (Blueprint $table) {
            $table->dropIndex('lead_tags_tag_lead_idx');
        });

        Schema::table('import_rows', function (Blueprint $table) {
            $table->dropIndex('import_rows_batch_lead_idx');
            $table->dropIndex('import_rows_lead_id_idx');
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_source_id_idx');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_city_idx');
            $table->dropIndex('companies_industry_idx');
        });
    }
};
