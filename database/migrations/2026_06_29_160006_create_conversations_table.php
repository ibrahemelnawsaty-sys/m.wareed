<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->index()->constrained()->cascadeOnDelete();
            $table->string('wa_contact_id')->index();
            $table->string('status')->default('open');
            $table->timestamp('window_expires_at')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_account_id', 'wa_contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
