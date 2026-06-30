<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The number of user seats a tenant is allowed (owner + agents). The ADMIN
     * alone sets this (Tenant::setMaxUsers via the admin console); it is NEVER
     * mass-assignable from tenant input — a self-raised seat limit would let an
     * owner bypass their plan (§13). Defaults to 3 so a fresh tenant can grow a
     * small team without admin action.
     */
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedInteger('max_users')->default(3)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('max_users');
        });
    }
};
