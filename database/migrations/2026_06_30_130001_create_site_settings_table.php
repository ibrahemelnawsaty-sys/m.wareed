<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Editable PUBLIC site content for the marketing landing page and its SEO
     * metadata (Phase 4h). DELIBERATELY separate from `settings`: that table
     * holds platform secrets encrypted at rest (§13); this one holds copy that
     * is meant to be seen by everyone, so `value` is stored as plaintext and is
     * never cast to `encrypted`.
     *
     * Not a tenant table — no tenant_id, no TenantScope: the public site is
     * global to the platform. Managed exclusively by the super-admin.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // Nullable: a blank field on save clears the row so the landing page
            // falls back to its hard-coded default (no broken copy, §3). longText
            // comfortably holds the longest editable field (descriptions/notices).
            $table->longText('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_settings');
    }
};
