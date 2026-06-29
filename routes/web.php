<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AnalyticsController as AdminAnalyticsController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Dashboard\AnalyticsController;
use App\Http\Controllers\Dashboard\BotSettingsController;
use App\Http\Controllers\Dashboard\ConversationController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\KnowledgeDocumentController;
use App\Http\Controllers\Dashboard\PlaygroundController;
use App\Http\Controllers\Dashboard\WhatsappAccountController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

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

    // Conversations (read-only monitoring). Binding resolves through TenantScope,
    // so a foreign conversation id 404s (§1).
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');

    // Usage analytics (read-only, tenant-scoped aggregates).
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');

    // Bot playground — ephemeral, never persists. The send endpoint is throttled
    // to protect the API key from abuse/cost spikes (§12, §13).
    Route::get('/playground', [PlaygroundController::class, 'index'])->name('playground.index');
    Route::post('/playground/send', [PlaygroundController::class, 'send'])
        ->middleware('throttle:10,1')
        ->name('playground.send');
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

    // Platform-wide analytics (all tenants).
    Route::get('/analytics', [AdminAnalyticsController::class, 'index'])->name('analytics.index');
});

require __DIR__.'/auth.php';
