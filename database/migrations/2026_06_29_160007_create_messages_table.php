<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->foreignId('conversation_id')->index()->constrained()->cascadeOnDelete();
            $table->string('wa_message_id')->nullable()->unique();
            $table->string('direction'); // 'in' | 'out'
            $table->string('type')->default('text');
            $table->text('body')->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->unsignedBigInteger('cost_micros')->default(0);
            $table->string('status')->default('received');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
