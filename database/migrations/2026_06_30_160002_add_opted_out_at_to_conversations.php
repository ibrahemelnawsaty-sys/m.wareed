<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6d — conversation-level opt-out for bulk messaging (Meta opt-out, §11).
 *
 * When a customer sends an unsubscribe keyword (إيقاف / الغاء الاشتراك / stop /
 * unsubscribe …) the conversation is stamped with `opted_out_at` and is then
 * excluded from every future bulk campaign. NULL ⇒ still opted in. The column is
 * written ONLY through the trusted Conversation::optOut() (forceFill+save), never
 * mass-assigned, so it cannot be cleared from request input (§13).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->timestamp('opted_out_at')->nullable()->after('window_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropColumn('opted_out_at');
        });
    }
};
