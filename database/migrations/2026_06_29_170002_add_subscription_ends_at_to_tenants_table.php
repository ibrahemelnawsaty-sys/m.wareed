<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Subscription expiry that, together with `status`, governs whether a
     * tenant's bot is allowed to reply (see Tenant::isActive()). Nullable: a
     * pending/free tenant has no expiry until an admin sets one. Set only by
     * trusted admin code — never mass-assignable (§13).
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('subscription_ends_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('subscription_ends_at');
        });
    }
};
