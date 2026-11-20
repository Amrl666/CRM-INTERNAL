<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('proposal_delivery_attempts', function (Blueprint $table) {
            $table->foreignUuid('campaign_recipient_channel_id')->nullable()->after('campaign_recipient_id')
                ->constrained('campaign_recipient_channels')->cascadeOnDelete();

            $table->index(['campaign_recipient_channel_id', 'attempted_at'], 'pda_channel_attempted_idx');
            $table->index(['channel', 'status', 'attempted_at'], 'pda_channel_status_attempted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('proposal_delivery_attempts', function (Blueprint $table) {
            $table->dropForeign(['campaign_recipient_channel_id']);
            $table->dropIndex('pda_channel_attempted_idx');
            $table->dropIndex('pda_channel_status_attempted_idx');
            $table->dropColumn('campaign_recipient_channel_id');
        });
    }
};
