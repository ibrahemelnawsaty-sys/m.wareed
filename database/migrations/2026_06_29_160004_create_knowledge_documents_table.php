<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->index()->constrained()->cascadeOnDelete();
            $table->foreignId('whatsapp_account_id')->index()->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('text');
            $table->longText('content')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
