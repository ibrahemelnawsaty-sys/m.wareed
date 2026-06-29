<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;

// H1 (admin review): the playground must honour the SAME activation gate as the
// webhook (§9). A pending / suspended / expired tenant cannot run the bot —
// otherwise it would drain the platform Gemini key without admin approval.

function bindlessAccountFor(string $status): User
{
    app(TenantContext::class)->forget();
    $tenant = Tenant::factory()->create(['status' => $status]);
    WhatsappAccount::factory()->create(['tenant_id' => $tenant->id]);
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);
    app(TenantContext::class)->forget();

    return $owner;
}

test('the playground refuses to send for a non-active tenant and calls no model', function () {
    config()->set('services.gemini.api_key', 'platform-key-x');
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['role' => 'model', 'parts' => [['text' => 'hi']]]]],
            'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
        ], 200),
    ]);

    $owner = bindlessAccountFor('pending');

    $this->actingAs($owner)
        ->postJson('/playground/send', ['message' => 'مرحبا'])
        ->assertForbidden();

    Http::assertNothingSent();
});

test('the playground page redirects a non-active tenant to the dashboard', function () {
    $owner = bindlessAccountFor('suspended');

    $this->actingAs($owner)->get('/playground')->assertRedirect(route('dashboard'));
});
