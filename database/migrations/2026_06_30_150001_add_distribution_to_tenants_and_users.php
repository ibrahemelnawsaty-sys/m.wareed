<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6c — conversation distribution modes (§11 handoff routing).
 *
 * `distribution_mode` selects how a handed-off conversation reaches an agent:
 *   - 'claim'    (default, Phase 6b behaviour): it stays UNASSIGNED in the queue
 *                and any agent claims it ("fastest wins").
 *   - 'balanced': on handoff it is auto-assigned to the least-loaded agent who is
 *                still under their target, and an agent at their target cannot be
 *                handed (or claim) more.
 *
 * `agent_conversation_quota` is the tenant-wide default target (open
 * conversations) per agent; `users.conversation_quota` overrides it for one
 * agent (NULL ⇒ inherit the tenant default). All three are ADMIN/OWNER-trusted
 * config, set only via Tenant::setDistribution / User::setConversationQuota
 * (save() on validated values) — NEVER mass-assignable, so an agent cannot raise
 * their own ceiling and an owner cannot smuggle the mode through request input
 * (§13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // 'claim' keeps every existing tenant on the current Phase 6b behaviour.
            $table->string('distribution_mode')->default('claim')->after('max_users');
            // Default target of 5 open conversations per agent in balanced mode.
            $table->unsignedInteger('agent_conversation_quota')->default(5)->after('distribution_mode');
        });

        Schema::table('users', function (Blueprint $table) {
            // Per-agent override of the tenant target; NULL ⇒ inherit tenant default.
            $table->unsignedInteger('conversation_quota')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('conversation_quota');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['distribution_mode', 'agent_conversation_quota']);
        });
    }
};
