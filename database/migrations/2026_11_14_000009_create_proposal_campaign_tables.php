<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposal_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->string('version', 30);
            $table->string('view_path');
            $table->jsonb('required_variables')->nullable();
            $table->jsonb('assets')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['slug', 'version']);
            $table->index(['slug', 'is_active']);
        });

        Schema::create('proposal_sender_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name');
            $table->string('sender_name');
            $table->string('sender_email', 320);
            $table->string('sender_phone', 30)->nullable();
            $table->string('sender_website', 255)->nullable();
            $table->text('footer_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index('is_active');
        });

        Schema::create('message_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120);
            $table->string('channel', 20);
            $table->string('subject')->nullable();
            $table->longText('body_html')->nullable();
            $table->longText('body_text')->nullable();
            $table->longText('whatsapp_body')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['channel', 'is_active']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('type', 20)->default('email')->after('name');
            $table->string('channel', 20)->nullable()->after('type');
            $table->uuid('template_id')->nullable()->change();
            $table->foreignUuid('proposal_template_id')->nullable()->after('template_id')
                ->constrained('proposal_templates')->nullOnDelete();
            $table->foreignUuid('message_template_id')->nullable()->after('proposal_template_id')
                ->constrained('message_templates')->nullOnDelete();
            $table->string('proposal_template_version', 30)->nullable();
            $table->text('subject_template')->nullable();
            $table->longText('body_html_template')->nullable();
            $table->longText('body_text_template')->nullable();
            $table->longText('whatsapp_template')->nullable();
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->jsonb('business_days')->default('[1,2,3,4,5]');
            $table->time('business_start')->default('09:00');
            $table->time('business_end')->default('17:00');
            $table->jsonb('excluded_dates')->default('[]');
            $table->unsignedInteger('rate_limit_per_hour')->default(10);
            $table->unsignedInteger('min_spacing_seconds')->default(120);
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedInteger('approved_revision')->nullable();
            $table->jsonb('approval_snapshot')->nullable();
            $table->timestampTz('scheduled_start_at')->nullable();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('paused_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->text('last_error')->nullable();
        });

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->string('eligibility_status', 20)->default('pending')->after('status');
            $table->text('eligibility_reason')->nullable()->after('eligibility_status');
            $table->string('pdf_status', 20)->default('PENDING')->after('eligibility_reason');
            $table->string('pdf_path')->nullable()->after('pdf_status');
            $table->string('proposal_number', 50)->nullable()->after('pdf_path');
            $table->date('proposal_date')->nullable()->after('proposal_number');
            $table->string('delivery_status', 30)->default('PENDING')->after('proposal_date');
            $table->timestampTz('scheduled_at')->nullable()->after('delivery_status');
            $table->timestampTz('processed_at')->nullable()->after('scheduled_at');
            $table->unsignedInteger('attempts')->default(0)->after('processed_at');
            $table->text('skip_reason')->nullable()->after('attempts');
            $table->text('last_error')->nullable()->after('skip_reason');
            $table->string('idempotency_key', 100)->nullable()->after('last_error');
            $table->string('provider_message_id')->nullable()->after('idempotency_key');
            $table->text('rendered_subject')->nullable();
            $table->longText('rendered_body_html')->nullable();
            $table->longText('rendered_body_text')->nullable();
            $table->longText('rendered_whatsapp')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_filename')->nullable();
            $table->jsonb('snapshot')->nullable();
            $table->timestampTz('cancelled_at')->nullable();

            $table->unique('idempotency_key');
            $table->unique('proposal_number');
            $table->index(['campaign_id', 'delivery_status']);
            $table->index(['campaign_id', 'scheduled_at']);
        });

        Schema::create('proposal_delivery_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campaign_recipient_id')->constrained('campaign_recipients')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('provider', 50)->nullable();
            $table->string('status', 30);
            $table->unsignedInteger('attempt_number');
            $table->string('provider_message_id')->nullable();
            $table->jsonb('request_meta')->nullable();
            $table->jsonb('response_meta')->nullable();
            $table->text('error')->nullable();
            $table->timestampTz('attempted_at');
            $table->timestampsTz();

            $table->index(['campaign_recipient_id', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_delivery_attempts');

        Schema::table('campaign_recipients', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropUnique(['proposal_number']);
            $table->dropIndex(['campaign_id', 'delivery_status']);
            $table->dropIndex(['campaign_id', 'scheduled_at']);
            $table->dropColumn([
                'eligibility_status', 'eligibility_reason', 'pdf_status', 'pdf_path',
                'proposal_number', 'proposal_date', 'delivery_status', 'scheduled_at',
                'processed_at', 'attempts', 'skip_reason', 'last_error', 'idempotency_key',
                'provider_message_id', 'rendered_subject', 'rendered_body_html',
                'rendered_body_text', 'rendered_whatsapp', 'attachment_path',
                'attachment_filename', 'snapshot', 'cancelled_at',
            ]);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropForeign(['proposal_template_id']);
            $table->dropForeign(['message_template_id']);
            $table->dropColumn([
                'type', 'channel', 'proposal_template_id', 'message_template_id',
                'proposal_template_version', 'subject_template', 'body_html_template',
                'body_text_template', 'whatsapp_template', 'timezone', 'business_days',
                'business_start', 'business_end', 'excluded_dates', 'rate_limit_per_hour',
                'min_spacing_seconds', 'revision', 'approved_revision', 'approval_snapshot',
                'scheduled_start_at', 'started_at', 'completed_at', 'paused_at',
                'cancelled_at', 'last_error',
            ]);
        });

        Schema::dropIfExists('message_templates');
        Schema::dropIfExists('proposal_sender_settings');
        Schema::dropIfExists('proposal_templates');
    }
};
