<?php

declare(strict_types=1);

use App\Http\Controllers\WhatsApp\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// WhatsApp Cloud API webhook (ADR-01, §11).
// GET handshake has no signature middleware (Meta sends no body to sign);
// POST is gated by the X-Hub-Signature-256 verification before any processing.
Route::get('/whatsapp/webhook', [WebhookController::class, 'verify']);
// throttle is a DoS backstop AFTER signature verification, so only validly
// signed (i.e. genuinely from Meta) requests count toward the limit (§11, §13).
Route::post('/whatsapp/webhook', [WebhookController::class, 'handle'])
    ->middleware(['whatsapp.signature', 'throttle:300,1']);
