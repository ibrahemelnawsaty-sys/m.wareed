<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7b — interactive service menu (WhatsApp List Message, §11).
 *
 * One menu per tenant: the owner configures a list (header/body/button/footer)
 * that the bot offers to the customer interactively. When `enabled` is false the
 * whole feature is dormant and inbound messages take the normal AI path.
 * `trigger_on_welcome` decides whether the menu is offered automatically on the
 * first inbound message of a brand-new conversation (otherwise it is sent only
 * when the customer asks for it — see MenuTriggerDetector).
 *
 * tenant-owned (BelongsToTenant) — isolated by TenantScope (§1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            // Meta list limits (§11): header ≤60, body ≤1024, button ≤20, footer ≤60.
            $table->string('header')->nullable();
            $table->string('body');
            $table->string('button_label');
            $table->string('footer')->nullable();
            $table->boolean('trigger_on_welcome')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_menus');
    }
};
