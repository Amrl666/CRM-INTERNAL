<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 20);
            $table->string('account_email', 320)->nullable();
            $table->string('display_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestampTz('token_expires_at')->nullable();
            $table->string('connection_status', 20)->default('disconnected');
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampsTz();
            $table->index(['provider', 'connection_status']);
        });

        Schema::create('provider_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider', 20);
            $table->string('event_id', 200);
            $table->string('event_type', 100)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_events');
        Schema::dropIfExists('communication_accounts');
    }
};
