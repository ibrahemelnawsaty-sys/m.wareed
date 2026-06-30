<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6d — one row per (campaign, conversation): the unit a SendBulkMessageJob
 * processes, plus the recorded outcome.
 *
 * status: pending → sent, or one of the skip reasons (skipped_window /
 * skipped_optout / skipped_cap) or failed. The skip reasons make the Meta-safety
 * decision auditable per contact: skipped_window ⇒ outside the 24h window so a
 * template is required (we NEVER free-form outside it, §11); skipped_optout ⇒
 * the contact unsubscribed; skipped_cap ⇒ the daily cap was reached mid-run.
 *
 * unique(bulk_campaign_id, conversation_id) makes a contact appear at most once
 * in a campaign — idempotent against a re-dispatched job. tenant-owned (§1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('bulk_campaign_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('wa_contact_id');
            $table->string('status')->default('pending');
            $table->string('wa_message_id')->nullable();
            $table->string('failed_reason')->nullable();
            $table->timestamps();

            $table->unique(['bulk_campaign_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_campaign_recipients');
    }
};
