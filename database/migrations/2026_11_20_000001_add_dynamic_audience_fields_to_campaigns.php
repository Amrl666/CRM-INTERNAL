<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->jsonb('audience_filters')->nullable()->after('approval_snapshot');
            $table->timestampTz('audience_snapshot_at')->nullable()->after('audience_filters');
            $table->uuid('audience_resolution_cursor')->nullable()->after('audience_snapshot_at');
            $table->timestampTz('audience_resolved_at')->nullable()->after('audience_resolution_cursor');
            $table->foreignUuid('whatsapp_message_template_id')->nullable()->after('message_template_id')
                ->constrained('message_templates')->nullOnDelete();

            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['whatsapp_message_template_id']);
            $table->dropIndex(['type', 'status']);
            $table->dropColumn([
                'audience_filters',
                'audience_snapshot_at',
                'audience_resolution_cursor',
                'audience_resolved_at',
                'whatsapp_message_template_id',
            ]);
        });
    }
};
