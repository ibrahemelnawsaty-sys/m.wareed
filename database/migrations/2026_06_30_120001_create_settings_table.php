<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Platform-wide settings managed by the super-admin (§13). The `value`
     * column holds admin-managed platform AI keys (gemini/openai/deepseek)
     * encrypted at the application layer (Setting::$casts), so the ciphertext
     * stored here is never the plaintext secret. This is NOT a tenant table — it
     * has no tenant_id and carries no TenantScope: a setting is global to the
     * platform, readable only inside the AI resolution layer.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            // Nullable: an admin may clear a platform key. Encrypted ciphertext
            // is longer than its plaintext, so `text` (not `string`) is used.
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
