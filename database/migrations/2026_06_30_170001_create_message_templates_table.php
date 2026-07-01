<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7c — Meta-approved message templates (§11, Meta number-safety).
 *
 * A template is a pre-approved message shape the owner registers in Meta Business
 * Manager; only templates whose `status` is 'approved' may be sent, and a template
 * send works even OUTSIDE the 24h service window (that is the whole point of
 * templates). The `status`/`category`/`variable_count`/`body_text` columns are the
 * cached mirror of Meta's truth, refreshed by TemplateSync — they are written ONLY
 * through trusted server logic (forceFill/updateOrCreate), never mass-assigned from
 * request input (§13). tenant-owned (BelongsToTenant) — isolated by TenantScope (§1).
 *
 * unique(whatsapp_account_id, name, language): Meta keys a template by name +
 * language, so re-syncing UPDATES the existing row rather than duplicating it (§3
 * non-destructive). `variable_count` is the number of {{n}} placeholders in the
 * body; a bulk campaign must supply exactly that many variables or Meta rejects it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('language'); // e.g. ar / en_US (Meta language code)
            $table->string('category')->default('utility'); // marketing/utility/authentication
            $table->string('status')->default('unknown'); // approved/pending/rejected/paused/disabled/unknown
            $table->text('body_text')->nullable(); // display copy with {{1}} placeholders
            $table->unsignedInteger('variable_count')->default(0);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            // Meta keys a template by (name, language); re-sync updates in place.
            $table->unique(['whatsapp_account_id', 'name', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
