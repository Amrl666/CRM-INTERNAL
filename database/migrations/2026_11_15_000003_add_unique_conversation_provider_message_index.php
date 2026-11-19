<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX communication_messages_conversation_provider_message_unique '.
            'ON communication_messages (conversation_id, provider_message_id) '.
            'WHERE conversation_id IS NOT NULL AND provider_message_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS communication_messages_conversation_provider_message_unique');
    }
};
