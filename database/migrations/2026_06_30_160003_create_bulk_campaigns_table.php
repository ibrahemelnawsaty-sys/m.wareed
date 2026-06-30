<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6d — a bulk-message campaign: one body the owner sends to many eligible
 * (opted-in, in-window) contacts under the daily cap (§11, Meta number-safety).
 *
 * status lifecycle: queued → sending → completed, or → stopped (the owner's
 * kill switch). The four counters mirror the per-recipient outcomes so the owner
 * sees, live, how many were sent vs. skipped (window/opt-out/cap) vs. failed.
 * tenant-owned (BelongsToTenant) — isolated by TenantScope (§1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('status')->default('queued');
            $table->unsignedInteger('recipients_total')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_campaigns');
    }
};
