<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_messages', function (Blueprint $table) {
            $table->foreignUuid('conversation_id')->nullable()->after('contact_id')
                ->constrained('conversations')->nullOnDelete();
            $table->jsonb('provider_payload')->nullable();
            $table->index('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('communication_messages', function (Blueprint $table) {
            $table->dropForeign(['conversation_id']);
            $table->dropIndex(['conversation_id']);
            $table->dropColumn(['conversation_id', 'provider_payload']);
        });
    }
};
