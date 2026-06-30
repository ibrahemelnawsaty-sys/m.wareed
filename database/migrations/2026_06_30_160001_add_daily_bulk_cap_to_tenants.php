<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6d — the per-tenant daily bulk-send cap (Meta number-protection, §11).
 *
 * `daily_bulk_cap` is the maximum number of bulk messages this tenant's number
 * may send in a single calendar day. It defaults to 250 — Meta's default
 * messaging limit for a fresh/unverified number — and is HARD-CAPPED at 250 in
 * code (see Tenant::setBulkCap / SendQuota): we never let a tenant raise it past
 * the conservative ceiling, protecting the customer's number from the bans that
 * follow exceeding Meta's limit. It is ADMIN/OWNER-trusted config, set only via
 * Tenant::setBulkCap (save() on a clamped value), NEVER mass-assignable (§13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedSmallInteger('daily_bulk_cap')->default(250)->after('agent_conversation_quota');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('daily_bulk_cap');
        });
    }
};
