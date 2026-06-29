<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI provider selector, defaulting to 'gemini' to match the current
     * Gemini-only stack. Added now to prepare for multi-provider support later
     * without a second schema change on a sensitive table (§1).
     */
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->string('ai_provider')->default('gemini')->after('ai_model');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn('ai_provider');
        });
    }
};
