<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6b — WhatsApp-like inbox + human handoff (§11).
 *
 * `mode` flips a conversation between the AI bot ('ai') and a human agent
 * ('human'); `assigned_to_user_id` claims it for one agent (atomic claim in
 * Conversation::claimBy prevents two agents grabbing the same thread).
 * `handoff_at` records when it left the bot. `contact_name` caches the
 * WhatsApp profile name for the inbox list. On messages, `user_id` records
 * which agent sent an outbound reply (NULL for inbound + bot replies).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('mode')->default('ai')->after('status');
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->after('mode')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('contact_name')->nullable()->after('wa_contact_id');
            $table->timestamp('handoff_at')->nullable()->after('window_expires_at');

            // Drives the inbox filters: human / mine / unassigned, scoped per tenant.
            $table->index(['tenant_id', 'mode', 'assigned_to_user_id'], 'conversations_inbox_index');
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('conversation_id')
                ->constrained('users')
                ->nullOnDelete();

            // The inbox poller hits /inbox/{conversation}/messages every 5s with
            // `where conversation_id = ? and id > ? order by id`. A composite
            // (conversation_id, id) index makes each poll an indexed range seek
            // from `after` onward instead of scanning the whole thread — keeping
            // the cost O(new rows), not O(thread length), on shared hosting (§14).
            $table->index(['conversation_id', 'id'], 'messages_conversation_poll_index');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('messages_conversation_poll_index');
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropIndex('conversations_inbox_index');
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropColumn(['mode', 'assigned_to_user_id', 'contact_name', 'handoff_at']);
        });
    }
};
