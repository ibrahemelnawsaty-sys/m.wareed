<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7b — the rows of a service menu (§11). Each row is one selectable option
 * the customer taps. `row_key` is the stable id Meta echoes back in the list
 * reply; it is generated server-side (never from user input) and is UNIQUE per
 * menu, so the inbound reply maps to exactly one row.
 *
 * `action_type` decides what happens when the row is tapped:
 *  - 'reply'   → send the canned `reply_text` and stay on the bot.
 *  - 'handoff' → flip the conversation to a human agent (skip the AI).
 *
 * tenant-owned (BelongsToTenant) — isolated by TenantScope (§1). Meta limits:
 * title ≤24, description ≤72, row id ≤200, ≤10 rows total (enforced in the
 * FormRequest before any row is written).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_menu_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('service_menu_id')->index()->constrained()->cascadeOnDelete();
            $table->string('row_key');
            $table->string('title');
            $table->string('description')->nullable();
            // 'reply' | 'handoff' — validated in the FormRequest.
            $table->string('action_type');
            $table->text('reply_text')->nullable();
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            // One row_key per menu: the list-reply id resolves to a single row.
            $table->unique(['service_menu_id', 'row_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_menu_rows');
    }
};
