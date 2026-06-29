<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log of platform-to-customer messages (§13). A super-admin sends an email
 * to a customer's owner from the admin console; every send is recorded here.
 *
 * This is an ADMIN-OWNED table: it is read/written ONLY from admin controllers
 * (which already cross tenants via withoutGlobalScopes). It carries `tenant_id`
 * to attribute each message to a customer, but the model deliberately does NOT
 * use BelongsToTenant — there is no tenant context in the admin surface.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_messages', function (Blueprint $table) {
            $table->id();

            // The customer (tenant) this message was sent to. Cascade so the
            // audit rows go away with the tenant; indexed for the per-customer
            // history list on the show page.
            $table->foreignId('tenant_id')
                ->index()
                ->constrained('tenants')
                ->cascadeOnDelete();

            // The super-admin who sent it. nullOnDelete so removing an admin
            // never destroys the audit trail (we keep the message, lose the
            // attribution).
            $table->foreignId('sent_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Channel is 'email' for now; the column leaves room for future
            // channels (e.g. whatsapp) without a schema change.
            $table->string('channel')->default('email');
            $table->string('subject');
            $table->text('body');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_messages');
    }
};
