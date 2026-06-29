<?php

declare(strict_types=1);

use App\Models\Message;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;

const PG_GEMINI_HOST = 'generativelanguage.googleapis.com';

function fakeGeminiOk(): void
{
    Http::fake([
        PG_GEMINI_HOST.'/*' => Http::response([
            'candidates' => [[
                'content' => ['role' => 'model', 'parts' => [['text' => 'مرحباً']]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => ['promptTokenCount' => 5, 'candidatesTokenCount' => 3],
        ], 200),
    ]);
}

// MEDIUM-1: the playground bypasses usage recording (ephemeral), so it must
// enforce its own per-tenant daily cap to keep live testing from draining the
// platform Gemini key (§12, §14).
test('the playground enforces a per-tenant daily cap', function () {
    config()->set('services.gemini.api_key', 'platform-key-x');
    config()->set('services.gemini.playground_daily_cap', 1);
    fakeGeminiOk();

    $owner = provisionTenant();
    app(TenantContext::class)->forget();

    // First try succeeds; the second exceeds the cap (429) without calling Gemini.
    $this->actingAs($owner)->postJson('/playground/send', ['message' => 'مرحبا'])->assertOk();
    $this->actingAs($owner)->postJson('/playground/send', ['message' => 'مرحبا'])->assertStatus(429);

    // Ephemeral guarantee still holds: nothing was persisted.
    expect(Message::query()->withoutGlobalScopes()->count())->toBe(0);
});

// Sanity: a normal try returns the reply and never persists a message.
test('a playground try returns a reply and persists nothing', function () {
    config()->set('services.gemini.api_key', 'platform-key-x');
    config()->set('services.gemini.playground_daily_cap', 200);
    fakeGeminiOk();

    $owner = provisionTenant();
    app(TenantContext::class)->forget();

    $this->actingAs($owner)
        ->postJson('/playground/send', ['message' => 'هل لديكم توصيل؟'])
        ->assertOk()
        ->assertJsonStructure(['reply', 'tokens_in', 'tokens_out']);

    expect(Message::query()->withoutGlobalScopes()->count())->toBe(0);
});
