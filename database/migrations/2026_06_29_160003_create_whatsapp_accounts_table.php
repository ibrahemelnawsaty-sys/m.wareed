<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->string('phone_number_id')->unique();
            $table->string('waba_id')->nullable();
            $table->string('display_name')->nullable();
            $table->text('access_token');
            $table->string('ai_model')->default('gemini-2.5-flash-lite');
            $table->text('ai_api_key')->nullable();
            $table->text('system_prompt')->nullable();
            $table->unsignedTinyInteger('temperature')->default(30);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_accounts');
    }
};
