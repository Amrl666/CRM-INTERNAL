<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX communication_messages_gmail_account_provider_message_unique
             ON communication_messages (account_id, provider_message_id)
             WHERE provider = \'gmail\'
               AND account_id IS NOT NULL
               AND provider_message_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS communication_messages_gmail_account_provider_message_unique'
        );
    }
};
