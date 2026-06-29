<?php

declare(strict_types=1);

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

require __DIR__.'/auth.php';
