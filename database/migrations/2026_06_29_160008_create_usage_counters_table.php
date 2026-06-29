<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->date('date');
            $table->unsignedInteger('messages')->default(0);
            $table->unsignedBigInteger('tokens_in')->default(0);
            $table->unsignedBigInteger('tokens_out')->default(0);
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
    }
};
