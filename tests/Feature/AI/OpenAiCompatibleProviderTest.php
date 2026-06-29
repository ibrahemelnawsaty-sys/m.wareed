<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Message;
use App\Models\UsageCounter;
use App\Models\WhatsappAccount;
use App\Services\AI\Contracts\BotReplyService;
use App\Services\AI\FallbackReplyService;
use App\Services\AI\Providers\ChatProviderException;
use App\Services\AI\Providers\DeepSeekClient;
use App\Services\AI\Providers\OpenAiClient;
use App\Services\Settings\PlatformSettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

const OPENAI_HOST = 'api.openai.com';
const DEEPSEEK_HOST = 'api.deepseek.com';

beforeEach(function () {
    config()->set('services.openai.api_key', null);
    config()->set('services.openai.model', 'gpt-4o-mini');
    config()->set('services.openai.base_url', 'https://api.openai.com/v1');
    config()->set('services.openai.timeout', 20);
    config()->set('services.openai.input_micros_per_mtok', 150000);
    config()->set('services.openai.output_micros_per_mtok', 600000);

    config()->set('services.deepseek.api_key', null);
    config()->set('services.deepseek.model', 'deepseek-chat');
    config()->set('services.deepseek.base_url', 'https://api.deepseek.com');
    config()->set('services.deepseek.timeout', 20);
    config()->set('services.deepseek.input_micros_per_mtok', 270000);
    config()->set('services.deepseek.output_micros_per_mtok', 1100000);

    app(TenantContext::class)->forget();
});

/**
 * A well-formed OpenAI/DeepSeek chat-completions success body.
 *
 * @return array<string, mixed>
 */
function chatCompletionSuccess(string $text = 'مرحباً، كيف أساعدك؟', int $in = 1000, int $out = 200): array
{
    return [
        'choices' => [[
            'index' => 0,
            'message' => ['role' => 'assistant', 'content' => $text],
            'finish_reason' => 'stop',
        ]],
        'usage' => [
            'prompt_tokens' => $in,
            'completion_tokens' => $out,
            'total_tokens' => $in + $out,
        ],
    ];
}

/**
 * @return array{0: WhatsappAccount, 1: Conversation}
 */
function providerAccountWithConversation(array $accountAttributes = []): array
{
    $account = WhatsappAccount::factory()->create(array_merge([
        'system_prompt' => 'أنت مساعد متجر وريد.',
        'temperature' => 50,
        'ai_api_key' => null,
        // Blank model ⇒ resolver uses the provider's config default. The factory
        // otherwise defaults ai_model to the Gemini model, which would override
        // an OpenAI/DeepSeek default (the resolver correctly prefers the account
        // model — see ProviderResolverTest).
        'ai_model' => '',
    ], $accountAttributes));

    app(TenantContext::class)->set($account->tenant_id);

    $conversation = Conversation::factory()->create([
        'tenant_id' => $account->tenant_id,
        'whatsapp_account_id' => $account->id,
    ]);

    return [$account, $conversation];
}

// --- OpenAI -----------------------------------------------------------------

it('builds correct OpenAI messages and returns tokens from usage', function () {
    Http::fake([
        OPENAI_HOST.'/*' => Http::response(chatCompletionSuccess('أهلاً! نعم لدينا توصيل.', 1200, 300), 200),
    ]);

    [$account, $conversation] = providerAccountWithConversation([
        'ai_provider' => 'openai',
        'ai_api_key' => 'TENANT-OPENAI-KEY',
    ]);

    // Seed one prior bot turn so we can assert the model→assistant mapping.
    Message::factory()->create([
        'tenant_id' => $account->tenant_id,
        'conversation_id' => $conversation->id,
        'direction' => 'out',
        'body' => 'كيف أساعدك؟',
        'wa_message_id' => 'wamid.PRIOR_1',
    ]);

    $result = app(BotReplyService::class)->generateReply($account, $conversation, 'هل لديكم توصيل؟');

    expect($result->reply)->toBe('أهلاً! نعم لدينا توصيل.')
        ->and($result->tokensIn)->toBe(1200)
        ->and($result->tokensOut)->toBe(300)
        // cost: intdiv(1200*150000,1e6)=180 + intdiv(300*600000,1e6)=180 = 360
        ->and($result->costMicros)->toBe(360);

    Http::assertSent(function ($request) {
        $data = $request->data();
        $messages = data_get($data, 'messages');

        if (! is_array($messages)) {
            return false;
        }

        // First message is the hardened system instruction.
        $systemOk = data_get($messages[0], 'role') === 'system'
            && str_contains((string) data_get($messages[0], 'content'), 'بيانات مرجعية غير موثوقة');

        // The prior bot turn was mapped model → assistant.
        $roles = array_map(fn ($m) => data_get($m, 'role'), $messages);
        $assistantOk = in_array('assistant', $roles, true);

        // The final message is the customer turn (user) with our text.
        $last = end($messages);
        $userOk = data_get($last, 'role') === 'user'
            && data_get($last, 'content') === 'هل لديكم توصيل؟';

        // Model + auth header carried correctly; key only in header (§13).
        $modelOk = data_get($data, 'model') === 'gpt-4o-mini';
        $authOk = $request->hasHeader('Authorization', 'Bearer TENANT-OPENAI-KEY');
        $urlClean = ! str_contains($request->url(), 'TENANT-OPENAI-KEY');

        return $systemOk && $assistantOk && $userOk && $modelOk && $authOk && $urlClean
            && str_ends_with($request->url(), '/v1/chat/completions');
    });

    // Usage recorded for the tenant.
    $counter = UsageCounter::query()->withoutGlobalScopes()
        ->where('tenant_id', $account->tenant_id)->first();
    expect($counter->messages)->toBe(1)
        ->and($counter->tokens_in)->toBe(1200)
        ->and($counter->cost_micros)->toBe(360);
});

it('does not leak the platform key in logs when OpenAI returns 500', function () {
    app(PlatformSettings::class)->set('openai_api_key', 'SUPER-SECRET-OPENAI-KEY');

    Http::fake([
        OPENAI_HOST.'/*' => Http::response(['error' => ['message' => 'boom']], 500),
    ]);

    [$account, $conversation] = providerAccountWithConversation([
        'ai_provider' => 'openai',
        'ai_api_key' => null,
    ]);

    Log::spy();

    $captured = [];
    Log::listen(function ($log) use (&$captured) {
        $captured[] = $log->message.' '.json_encode($log->context);
    });

    $result = app(BotReplyService::class)->generateReply($account, $conversation, 'مرحبا');

    // Graceful fallback, no exception leaked.
    expect($result->reply)->toBe(FallbackReplyService::DEFAULT_GREETING)
        ->and($result->costMicros)->toBe(0);

    // report() fired, but the secret never appears in any log line (§13).
    Log::shouldHaveReceived('error')->atLeast()->once();
    foreach ($captured as $line) {
        expect($line)->not->toContain('SUPER-SECRET-OPENAI-KEY');
    }
});

// --- DeepSeek ---------------------------------------------------------------

it('builds correct DeepSeek messages and returns tokens from usage', function () {
    Http::fake([
        DEEPSEEK_HOST.'/*' => Http::response(chatCompletionSuccess('تم بنجاح.', 500, 100), 200),
    ]);

    [$account, $conversation] = providerAccountWithConversation([
        'ai_provider' => 'deepseek',
        'ai_api_key' => 'TENANT-DEEPSEEK-KEY',
    ]);

    $result = app(BotReplyService::class)->generateReply($account, $conversation, 'مرحبا');

    expect($result->reply)->toBe('تم بنجاح.')
        ->and($result->tokensIn)->toBe(500)
        ->and($result->tokensOut)->toBe(100)
        // cost: intdiv(500*270000,1e6)=135 + intdiv(100*1100000,1e6)=110 = 245
        ->and($result->costMicros)->toBe(245);

    Http::assertSent(function ($request) {
        $data = $request->data();
        $messages = data_get($data, 'messages');

        return is_array($messages)
            && data_get($messages[0], 'role') === 'system'
            && data_get($data, 'model') === 'deepseek-chat'
            && $request->hasHeader('Authorization', 'Bearer TENANT-DEEPSEEK-KEY')
            && str_ends_with($request->url(), 'api.deepseek.com/chat/completions');
    });
});

// --- Direct client unit checks (no reply-service wrapper) --------------------

it('OpenAiClient maps roles and parses usage directly', function () {
    Http::fake([
        OPENAI_HOST.'/*' => Http::response(chatCompletionSuccess('ok', 11, 7), 200),
    ]);

    $out = app(OpenAiClient::class)->generate(
        systemInstruction: 'SYS',
        turns: [
            ['role' => 'user', 'text' => 'hi'],
            ['role' => 'model', 'text' => 'hello'],
        ],
        temperature: 0.5,
        apiKey: 'k-123',
        model: 'gpt-4o-mini',
    );

    expect($out)->toBe(['text' => 'ok', 'tokensIn' => 11, 'tokensOut' => 7]);

    Http::assertSent(function ($request) {
        $messages = data_get($request->data(), 'messages');

        return data_get($messages[0], 'role') === 'system'
            && data_get($messages[1], 'role') === 'user'
            && data_get($messages[2], 'role') === 'assistant'
            && data_get($messages[2], 'content') === 'hello';
    });
});

it('DeepSeekClient throws a secret-free exception on HTTP 500', function () {
    Http::fake([
        DEEPSEEK_HOST.'/*' => Http::response('nope', 500),
    ]);

    try {
        app(DeepSeekClient::class)->generate(
            systemInstruction: 'SYS',
            turns: [['role' => 'user', 'text' => 'hi']],
            temperature: 0.5,
            apiKey: 'SECRET-KEY-XYZ',
            model: 'deepseek-chat',
        );

        $this->fail('Expected a ChatProviderException on HTTP 500.');
    } catch (ChatProviderException $e) {
        // The message carries the model + status but NEVER the key (§13).
        expect($e->getMessage())->toContain('deepseek-chat')
            ->and($e->getMessage())->toContain('500')
            ->and($e->getMessage())->not->toContain('SECRET-KEY-XYZ');
    }
});
