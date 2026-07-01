<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-tenant Meta App Secret. Each tenant now owns its own Meta app, so
     * the webhook signature check can no longer rely solely on the single
     * platform-wide `services.whatsapp.app_secret` (§1, ADR-01). Nullable:
     * existing/onboarding tenants keep validating against the platform
     * secret until they paste their own on the connect page (non-destructive
     * rollout, §3). Encrypted at rest via the model cast (§13).
     */
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->text('app_secret')->nullable()->after('access_token');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropColumn('app_secret');
        });
    }
};
