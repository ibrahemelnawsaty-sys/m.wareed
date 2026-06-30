<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 6d — the atomic daily send ledger that enforces the cap (§11, §13).
 *
 * One row per (whatsapp_account, calendar day) holds `sent_count`. A reservation
 * is a single conditional UPDATE — `... SET sent_count = sent_count + 1 WHERE id
 * = ? AND sent_count < :cap` — so even under concurrent queue workers the cap can
 * never be exceeded (the row lock serialises the increment; an over-cap update
 * affects 0 rows). See App\Services\Bulk\SendQuota.
 *
 * unique(whatsapp_account_id, send_date) guarantees exactly one ledger row per
 * number per day, so all jobs contend on the SAME row. tenant_id is carried for
 * isolation/audit but the uniqueness/atomicity key is the account+date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_send_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->constrained()->cascadeOnDelete();
            $table->date('send_date');
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamps();

            $table->unique(['whatsapp_account_id', 'send_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_send_counters');
    }
};
