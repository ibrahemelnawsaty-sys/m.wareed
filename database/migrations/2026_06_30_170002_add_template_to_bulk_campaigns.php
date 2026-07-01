<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 7c — let a bulk campaign send a Meta-approved TEMPLATE instead of free
 * text, so it reaches contacts even outside the 24h window (§11).
 *
 * `message_template_id` is nullable: a NULL campaign is the existing free-form
 * path (window + sendText), a set one is the template path (sendTemplate, window
 * check skipped). nullOnDelete so deleting a template never cascades away the
 * historical campaigns that used it (audit, §3). `template_variables` is the JSON
 * array of body-parameter strings the owner supplied; its length must equal the
 * template's variable_count (validated server-side before the campaign is built).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bulk_campaigns', function (Blueprint $table) {
            $table->foreignId('message_template_id')
                ->nullable()
                ->after('user_id')
                ->constrained()
                ->nullOnDelete();
            $table->json('template_variables')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('bulk_campaigns', function (Blueprint $table) {
            $table->dropForeign(['message_template_id']);
            $table->dropColumn(['message_template_id', 'template_variables']);
        });
    }
};
