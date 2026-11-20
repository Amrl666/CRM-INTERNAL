<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipient_channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_recipient_id')->constrained('campaign_recipients')->cascadeOnDelete();
            $table->foreignUuid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignUuid('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->string('channel', 20);

            $table->string('eligibility_status', 20)->default('pending');
            $table->text('eligibility_reason')->nullable();
            $table->string('delivery_status', 30)->default('PENDING');
            $table->timestampTz('scheduled_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('skip_reason')->nullable();
            $table->text('last_error')->nullable();

            $table->string('idempotency_key', 150)->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->foreignUuid('message_id')->nullable()->constrained('communication_messages')->nullOnDelete();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->unique(['campaign_recipient_id', 'channel']);
            $table->unique('idempotency_key');
            $table->index(['campaign_id', 'channel', 'delivery_status'], 'crc_campaign_channel_status_idx');
            $table->index(['campaign_id', 'scheduled_at'], 'crc_campaign_scheduled_idx');
            $table->index(['contact_id', 'channel', 'delivery_status'], 'crc_contact_channel_status_idx');
            $table->index(['lead_id', 'channel', 'delivery_status'], 'crc_lead_channel_status_idx');
            $table->index(['delivery_status', 'updated_at'], 'crc_status_updated_idx');
        });

        // only one in-flight send per contact/channel across all campaigns.
        DB::unprepared(<<<'SQL'
            CREATE UNIQUE INDEX campaign_recipient_channel_processing_unique
            ON campaign_recipient_channels (contact_id, channel)
            WHERE delivery_status = 'PROCESSING' AND contact_id IS NOT NULL
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP INDEX IF EXISTS campaign_recipient_channel_processing_unique');
        Schema::dropIfExists('campaign_recipient_channels');
    }
};
