<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 50);
            $table->string('instance');
            $table->string('remote_jid');
            $table->string('phone_e164', 20)->nullable();
            $table->string('display_name')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignUuid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignUuid('lead_id')->nullable()->constrained('leads')->nullOnDelete();
            $table->timestampTz('last_message_at')->nullable();
            $table->text('last_message_preview')->nullable();
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampsTz();

            $table->unique(['provider', 'instance', 'remote_jid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
