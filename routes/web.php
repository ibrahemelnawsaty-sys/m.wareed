<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SiteController as AdminSiteController;
use App\Http\Controllers\Dashboard\AnalyticsController;
use App\Http\Controllers\Dashboard\BotSettingsController;
use App\Http\Controllers\Dashboard\BulkCampaignController;
use App\Http\Controllers\Dashboard\ConversationController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\InboxController;
use App\Http\Controllers\Dashboard\KnowledgeDocumentController;
use App\Http\Controllers\Dashboard\PlaygroundController;
use App\Http\Controllers\Dashboard\TeamController;
use App\Http\Controllers\Dashboard\WhatsappAccountController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Public SEO endpoints (§11)
|--------------------------------------------------------------------------
| Unauthenticated, read-only. robots.txt allows indexing in PRODUCTION ONLY;
| every other environment returns Disallow: / so staging/preview is never
| indexed (§11). The sitemap lists only public marketing/auth pages.
*/
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

/*
|--------------------------------------------------------------------------
| Dashboard (tenant-scoped)
|--------------------------------------------------------------------------
| Every panel route runs behind `auth` + `tenant` (BindTenant). BindTenant
| binds the signed-in user's tenant_id into TenantContext so the TenantScope
| global scope filters every query — this is the load-bearing isolation
| guarantee (§1). Without `tenant`, the scope is inert.
*/
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('verified')->name('dashboard');

    // Profile — every team member manages their own.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Conversations (read-only monitoring) — agents handle these. Binding
    // resolves through TenantScope, so a foreign conversation id 404s (§1).
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');

    // Inbox — WhatsApp-like human handoff (Phase 6b). Open to owner AND agents
    // (NOT inside the `owner` group): an agent's core job is to take over and
    // reply by hand. Every {conversation} resolves through TenantScope, so a
    // foreign id 404s (§1). Authorization (whose conversation / window) is
    // enforced inside the controller (§13).
    Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/{conversation}', [InboxController::class, 'show'])->name('inbox.show');
    Route::get('/inbox/{conversation}/messages', [InboxController::class, 'messages'])->name('inbox.messages');
    Route::post('/inbox/{conversation}/reply', [InboxController::class, 'reply'])->name('inbox.reply');
    Route::post('/inbox/{conversation}/claim', [InboxController::class, 'claim'])->name('inbox.claim');
    Route::post('/inbox/{conversation}/release', [InboxController::class, 'release'])->name('inbox.release');

    // Usage analytics (read-only, tenant-scoped aggregates).
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    /*
    |--------------------------------------------------------------------------
    | Account administration — OWNER ONLY (§1, §13, least privilege)
    |--------------------------------------------------------------------------
    | An agent (role=agent) is blocked here: these surfaces touch the encrypted
    | WhatsApp token, the bot prompt, the knowledge base, the metered playground,
    | and team seats — none of which a non-owner team member should reach. They
    | are gated by `owner` on top of `auth`+`tenant`.
    */
    Route::middleware('owner')->group(function () {
        // WhatsApp connection (single account per tenant)
        Route::get('/whatsapp', [WhatsappAccountController::class, 'edit'])->name('whatsapp.edit');
        Route::put('/whatsapp', [WhatsappAccountController::class, 'update'])->name('whatsapp.update');

        // Bot settings
        Route::get('/bot', [BotSettingsController::class, 'edit'])->name('bot.edit');
        Route::put('/bot', [BotSettingsController::class, 'update'])->name('bot.update');

        // Knowledge base
        Route::resource('knowledge', KnowledgeDocumentController::class)
            ->parameters(['knowledge' => 'document'])
            ->except(['show']);

        // Bot playground — ephemeral; the send endpoint is throttled to protect
        // the metered API key from abuse/cost spikes (§12, §13).
        Route::get('/playground', [PlaygroundController::class, 'index'])->name('playground.index');
        Route::post('/playground/send', [PlaygroundController::class, 'send'])
            ->middleware('throttle:10,1')
            ->name('playground.send');

        // Team management — owner adds/removes agents up to the admin-set seat
        // ceiling. destroy's {user} resolves through TenantScope (foreign → 404).
        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        // Conversation distribution settings (Phase 6c): the mode + default
        // per-agent target, then a per-agent target override. All owner-only;
        // {user} resolves through TenantScope (foreign → 404). The static
        // `/team/distribution` is declared before `/team/{user}/quota` so it is
        // never captured by the {user} wildcard.
        Route::put('/team/distribution', [TeamController::class, 'updateDistribution'])->name('team.distribution');
        Route::put('/team/{user}/quota', [TeamController::class, 'updateAgentQuota'])->name('team.quota');
        Route::delete('/team/{user}', [TeamController::class, 'destroy'])->name('team.destroy');

        // Bulk messaging (Phase 6d) — SENSITIVE: touches customers' numbers and
        // can get a number banned, so it is owner-only and every Meta guard
        // (opt-in, 250 cap, 24h window, opt-out) is enforced server-side. The
        // static {campaign}/stop is fine after the {campaign} wildcard since both
        // are numeric ids. {campaign} resolves through TenantScope (foreign →404).
        Route::get('/bulk', [BulkCampaignController::class, 'index'])->name('bulk.index');
        Route::post('/bulk', [BulkCampaignController::class, 'store'])->name('bulk.store');
        Route::get('/bulk/{campaign}', [BulkCampaignController::class, 'show'])
            ->whereNumber('campaign')->name('bulk.show');
        Route::post('/bulk/{campaign}/stop', [BulkCampaignController::class, 'stop'])
            ->whereNumber('campaign')->name('bulk.stop');
        // Reversibility for a mistaken opt-out (§9). {conversation} resolves
        // through TenantScope (foreign → 404); the static 'optouts' segment keeps
        // it clear of the numeric {campaign} routes above.
        Route::post('/bulk/optouts/{conversation}/resubscribe', [BulkCampaignController::class, 'resubscribe'])
            ->whereNumber('conversation')->name('bulk.resubscribe');
    });
});

/*
|--------------------------------------------------------------------------
| Super-admin (cross-tenant)
|--------------------------------------------------------------------------
| Deliberately NOT behind `tenant` (BindTenant): the platform owner has no
| tenant_id and operates across every tenant (§1, §13). Access is locked to
| genuine super-admins by the `admin` gate, and any cross-tenant reads live
| only inside admin controllers (withoutGlobalScopes), never in tenant code.
| Phase 4a ships only this placeholder dashboard; management screens follow.
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    // Cross-tenant overview (counts + recent signups). Every figure inside the
    // controller is gathered with withoutGlobalScopes() — the audited exception.
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

    // Customer (tenant) management. Bound by id only (not by route-model scoping)
    // because the admin has no tenant context; the controller resolves each
    // tenant via withoutGlobalScopes()->findOrFail().
    Route::get('/customers', [AdminCustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{tenant}', [AdminCustomerController::class, 'show'])
        ->whereNumber('tenant')->name('customers.show');

    // Mutating actions — POST/PUT only, CSRF-protected, FormRequest-validated,
    // and routed through the trusted Tenant/account methods (no mass assignment
    // of status / subscription_ends_at, §13).
    Route::post('/customers/{tenant}/approve', [AdminCustomerController::class, 'approve'])
        ->whereNumber('tenant')->name('customers.approve');
    Route::post('/customers/{tenant}/suspend', [AdminCustomerController::class, 'suspend'])
        ->whereNumber('tenant')->name('customers.suspend');
    Route::post('/customers/{tenant}/unsuspend', [AdminCustomerController::class, 'unsuspend'])
        ->whereNumber('tenant')->name('customers.unsuspend');
    Route::put('/customers/{tenant}/subscription', [AdminCustomerController::class, 'updateSubscription'])
        ->whereNumber('tenant')->name('customers.subscription');
    Route::put('/customers/{tenant}/bot', [AdminCustomerController::class, 'updateBot'])
        ->whereNumber('tenant')->name('customers.bot');
    // Seat limit (max_users) — admin-only; setMaxUsers via trusted save(), never
    // mass assignment (§13). The tenant owner can never raise their own limit.
    Route::put('/customers/{tenant}/seats', [AdminCustomerController::class, 'updateSeats'])
        ->whereNumber('tenant')->name('customers.seats');

    // Email a customer (their tenant owner) from the admin console; every send
    // is recorded in the customer_messages audit log. Channel is 'email' only
    // for now (Phase 4d-1); CSRF + FormRequest-validated.
    Route::post('/customers/{tenant}/messages', [AdminCustomerController::class, 'sendMessage'])
        ->whereNumber('tenant')->name('customers.messages.store');

    // Platform-wide analytics (all tenants).
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics.index');

    // Platform AI keys (gemini/openai/deepseek). Admin-only, CSRF + FormRequest.
    // The keys are secrets: the edit page renders presence only, and a blank
    // field on update keeps the stored key (non-destructive, §3, §13).
    Route::get('/settings', [AdminSettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->name('settings.update');

    // Public site content (landing-page copy + SEO metadata, Phase 4h). Unlike
    // the AI keys above these are PUBLIC copy, not secrets, so the edit page
    // renders the live values for editing. CSRF + FormRequest; a blank field
    // reverts that field to its hard-coded landing default (§3).
    Route::get('/site', [AdminSiteController::class, 'edit'])->name('site.edit');
    Route::put('/site', [AdminSiteController::class, 'update'])->name('site.update');
});

require __DIR__.'/auth.php';
