<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_messages', function (Blueprint $table) {
            $table->string('sender_email', 255)->nullable();
            $table->string('sender_name', 255)->nullable();

            $table->jsonb('to_recipients')->nullable();
            $table->jsonb('cc_recipients')->nullable();
            $table->jsonb('bcc_recipients')->nullable();

            $table->jsonb('gmail_label_ids')->nullable();

            $table->timestampTz('received_at')->nullable();

            // Nullable karena data lama dan pesan outbound belum tentu
            // memiliki status read/starred dari Gmail.
            $table->boolean('is_read')->nullable();
            $table->boolean('is_starred')->nullable();

            $table->index(
                ['account_id', 'channel', 'received_at'],
                'comm_messages_gmail_inbox_idx'
            );

            $table->index(
                'thread_id',
                'comm_messages_gmail_thread_idx'
            );
        });

        Schema::table('communication_accounts', function (Blueprint $table) {
            // Gmail History API cursor untuk incremental synchronization.
            // Disimpan sebagai string agar tidak bergantung pada batas BIGINT.
            $table->string('gmail_history_id', 255)->nullable();
        });

        // GIN index untuk pencarian berdasarkan Gmail label IDs.
        DB::statement(
            'CREATE INDEX comm_messages_gmail_labels_gin_idx
             ON communication_messages
             USING GIN (gmail_label_ids)'
        );
    }

    public function down(): void
    {
        DB::statement(
            'DROP INDEX IF EXISTS comm_messages_gmail_labels_gin_idx'
        );

        Schema::table('communication_messages', function (Blueprint $table) {
            $table->dropIndex('comm_messages_gmail_inbox_idx');
            $table->dropIndex('comm_messages_gmail_thread_idx');

            $table->dropColumn([
                'sender_email',
                'sender_name',
                'to_recipients',
                'cc_recipients',
                'bcc_recipients',
                'gmail_label_ids',
                'received_at',
                'is_read',
                'is_starred',
            ]);
        });

        Schema::table('communication_accounts', function (Blueprint $table) {
            $table->dropColumn('gmail_history_id');
        });
    }
};
