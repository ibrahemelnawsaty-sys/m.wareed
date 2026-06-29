<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\UsageCounter;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

const PLAYGROUND_GEMINI_HOST = 'generativelanguage.googleapis.com';

beforeEach(function () {
    config()->set('services.gemini.api_key', 'platform-key-should-never-leak');
    config()->set('services.gemini.model', 'gemini-2.5-flash-lite');
    config()->set('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta');

    app(TenantContext::class)->forget();
});

/**
 * A well-formed Gemini generateContent success body.
 *
 * @return array<string, mixed>
 */
function playgroundGeminiSuccess(string $text = 'مرحباً، كيف أساعدك؟', int $in = 120, int $out = 45): array
{
    return [
        'candidates' => [[
            'content' => ['role' => 'model', 'parts' => [['text' => $text]]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => [
            'promptTokenCount' => $in,
            'candidatesTokenCount' => $out,
            'totalTokenCount' => $in + $out,
        ],
    ];
}

test('playground returns the reply and tokens without persisting anything', function () {
    Http::fake([
        PLAYGROUND_GEMINI_HOST.'/*' => Http::response(playgroundGeminiSuccess('أهلاً! نعم متاح.', 130, 50), 200),
    ]);

    $owner = provisionTenant(['system_prompt' => 'أنت مساعد متجر وريد.', 'temperature' => 50]);

    $response = $this->actingAs($owner)
        ->postJson(route('playground.send'), ['message' => 'هل المنتج متاح؟'])
        ->assertOk()
        ->assertJson([
            'reply' => 'أهلاً! نعم متاح.',
            'tokens_in' => 130,
            'tokens_out' => 50,
        ]);

    // The system prompt must NEVER be exposed to the client (§13).
    $response->assertDontSee('أنت مساعد متجر وريد', false);

    // EPHEMERAL: nothing persisted, no usage recorded (§3, §4). Count without
    // scopes so we catch any row created under ANY tenant.
    expect(Message::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Conversation::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(UsageCounter::query()->withoutGlobalScopes()->count())->toBe(0);
});

test('playground returns a polite JSON error on Gemini failure without leaking the key', function () {
    config()->set('services.gemini.api_key', 'SUPER-SECRET-PLATFORM-KEY');

    Http::fake([
        PLAYGROUND_GEMINI_HOST.'/*' => Http::response(['error' => ['message' => 'boom']], 500),
    ]);

    $owner = provisionTenant(['ai_api_key' => null]);

    $captured = [];
    Log::listen(function ($log) use (&$captured) {
        $captured[] = $log->message.' '.json_encode($log->context);
    });

    $response = $this->actingAs($owner)
        ->postJson(route('playground.send'), ['message' => 'مرحبا'])
        ->assertStatus(502)
        ->assertJsonStructure(['message']);

    // No key in the JSON body, and no row written.
    $response->assertDontSee('SUPER-SECRET-PLATFORM-KEY', false);
    expect(Message::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(UsageCounter::query()->withoutGlobalScopes()->count())->toBe(0);

    foreach ($captured as $line) {
        expect($line)->not->toContain('SUPER-SECRET-PLATFORM-KEY');
    }
});

test('playground uses the current tenant account only', function () {
    Http::fake([
        PLAYGROUND_GEMINI_HOST.'/*' => Http::response(playgroundGeminiSuccess(), 200),
    ]);

    // The signed-in tenant's account carries a recognisable persona + key.
    $owner = provisionTenant([
        'system_prompt' => 'بصمة-المستأجر-الحالي',
        'ai_api_key' => 'TENANT-A-KEY',
        'temperature' => 60,
    ]);

    $this->actingAs($owner)
        ->postJson(route('playground.send'), ['message' => 'اختبار'])
        ->assertOk();

    Http::assertSent(function ($request) {
        $system = (string) data_get($request->data(), 'systemInstruction.parts.0.text');

        // Built from THIS tenant's persona, sent with THIS tenant's key.
        return str_contains($system, 'بصمة-المستأجر-الحالي')
            && str_contains($request->url(), 'key=TENANT-A-KEY');
    });
});

test('playground requires a non-empty message', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)
        ->postJson(route('playground.send'), ['message' => ''])
        ->assertStatus(422)
        ->assertJsonValidationErrors('message');
});

test('the playground page loads for the tenant', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)->get(route('playground.index'))->assertOk();
});
