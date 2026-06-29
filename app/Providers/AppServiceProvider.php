<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AI\AiReplyService;
use App\Services\AI\Contracts\BotReplyService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);

        // Real bot reply provider: multi-provider (gemini/openai/deepseek),
        // selected per account by the admin (ADR-04, §12). It keeps
        // FallbackReplyService as an internal safety net on failure/timeout.
        $this->app->bind(BotReplyService::class, AiReplyService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
