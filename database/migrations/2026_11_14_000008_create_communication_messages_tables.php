<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('campaign_id')->nullable();
            $table->foreignUuid('account_id')->nullable();
            $table->string('channel', 20);
            $table->string('direction', 10)->default('outbound');
            $table->string('subject', 500)->nullable();
            $table->text('body')->nullable();
            $table->string('provider', 50)->nullable();
            $table->string('provider_message_id', 255)->nullable();
            $table->string('thread_id', 255)->nullable();
            $table->string('status', 30)->default('draft');
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampsTz();

            $table->index(['channel', 'status']);
            $table->index(['contact_id', 'created_at']);
        });

        Schema::create('message_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->nullable()->constrained('communication_messages')->cascadeOnDelete();
            $table->string('event_type', 100);
            $table->jsonb('payload')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestampsTz();

            $table->index(['message_id', 'event_type']);
        });

        // ponytail: campaign recipients keeps provider_draft_id for backward compat and legacy flow.
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->foreignUuid('message_id')->nullable()->after('provider_draft_id')
                ->constrained('communication_messages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropForeign(['message_id']);
            $table->dropColumn('message_id');
        });
        Schema::dropIfExists('message_events');
        Schema::dropIfExists('communication_messages');
    }
};
