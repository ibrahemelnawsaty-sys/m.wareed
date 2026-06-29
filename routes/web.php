<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\BotSettingsController;
use App\Http\Controllers\Dashboard\KnowledgeDocumentController;
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
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->middleware('verified')->name('dashboard');

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
});

require __DIR__.'/auth.php';
