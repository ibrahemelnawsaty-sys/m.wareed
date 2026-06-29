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
            // Nullable + unique: empty at onboarding, filled on the connect page.
            // Multiple NULLs are allowed by a unique index (MySQL/SQLite), so
            // every freshly-onboarded tenant can coexist before linking a number.
            $table->string('phone_number_id')->nullable()->unique();
            $table->string('waba_id')->nullable();
            $table->string('display_name')->nullable();
            // Nullable: a tenant's account is created at onboarding (status
            // 'pending') before the owner has pasted their WhatsApp token on the
            // connect page (§10). The token is added later via PUT /whatsapp.
            $table->text('access_token')->nullable();
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
