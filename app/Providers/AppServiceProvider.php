<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\AI\Contracts\BotReplyService;
use App\Services\AI\GeminiReplyService;
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

        // Real bot reply provider: Gemini 2.5 Flash-Lite (ADR-04, §12). It keeps
        // FallbackReplyService as an internal safety net on failure/timeout.
        $this->app->bind(BotReplyService::class, GeminiReplyService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
