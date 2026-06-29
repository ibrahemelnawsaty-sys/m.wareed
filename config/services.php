<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // WhatsApp Cloud API (Meta) — ADR-01, §11. الأسرار من .env فقط.
    'whatsapp' => [
        'app_secret' => env('WHATSAPP_APP_SECRET'),
        'verify_token' => env('WHATSAPP_VERIFY_TOKEN'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v21.0'),
        'graph_base' => env('WHATSAPP_GRAPH_BASE', 'https://graph.facebook.com'),
    ],

    // Gemini (Google Generative AI) — ADR-04, §12. المفتاح من .env فقط.
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash-lite'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
        'timeout' => (int) env('GEMINI_TIMEOUT', 20),

        // Number of past conversation turns (messages) sent as context (§12,§14).
        // Last N only — never the full history — to bound latency and cost.
        'history_turns' => (int) env('GEMINI_HISTORY_TURNS', 10),

        // Max characters of injected knowledge in the system instruction (ADR-04).
        // Flash-Lite supports a large context, but we cap to bound cost/latency.
        'knowledge_char_limit' => (int) env('GEMINI_KNOWLEDGE_CHAR_LIMIT', 8000),

        // Pricing per 1,000,000 tokens, expressed in micro-USD (integers, §3).
        // gemini-2.5-flash-lite: input $0.10 /Mtok, output $0.40 /Mtok.
        // 100000 micro-USD = $0.10 ; 400000 micro-USD = $0.40.
        'input_micros_per_mtok' => (int) env('GEMINI_INPUT_MICROS_PER_MTOK', 100000),
        'output_micros_per_mtok' => (int) env('GEMINI_OUTPUT_MICROS_PER_MTOK', 400000),

        // Per-tenant daily message cap (§12). 0 = disabled. When > 0 and a
        // tenant reaches it, the bot replies with the neutral fallback instead
        // of calling (and billing) Gemini.
        'daily_message_cap' => (int) env('GEMINI_DAILY_MESSAGE_CAP', 0),

        // Per-tenant daily cap for the bot playground (§12, §14). The playground
        // is ephemeral and bypasses usage recording, so it needs its own bound
        // to keep live testing from draining the platform key.
        'playground_daily_cap' => (int) env('GEMINI_PLAYGROUND_DAILY_CAP', 200),
    ],

    // OpenAI Chat Completions — multi-provider AI (§12). The .env key is the
    // last-resort fallback; the admin-managed platform key (encrypted in the
    // `settings` table) and the tenant's own encrypted key take precedence.
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        'timeout' => (int) env('OPENAI_TIMEOUT', 20),

        // Pricing per 1,000,000 tokens, in micro-USD (integers, §3). Approximate
        // gpt-4o-mini list pricing: input $0.15 /Mtok, output $0.60 /Mtok.
        'input_micros_per_mtok' => (int) env('OPENAI_INPUT_MICROS_PER_MTOK', 150000),
        'output_micros_per_mtok' => (int) env('OPENAI_OUTPUT_MICROS_PER_MTOK', 600000),
    ],

    // DeepSeek — OpenAI-compatible Chat Completions (§12). Same key-resolution
    // precedence as OpenAI; the .env key is the last-resort fallback.
    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
        'timeout' => (int) env('DEEPSEEK_TIMEOUT', 20),

        // Pricing per 1,000,000 tokens, in micro-USD (integers, §3). Approximate
        // deepseek-chat pricing: input $0.27 /Mtok, output $1.10 /Mtok.
        'input_micros_per_mtok' => (int) env('DEEPSEEK_INPUT_MICROS_PER_MTOK', 270000),
        'output_micros_per_mtok' => (int) env('DEEPSEEK_OUTPUT_MICROS_PER_MTOK', 1100000),
    ],

];
