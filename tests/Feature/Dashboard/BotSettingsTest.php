<?php

declare(strict_types=1);

use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;

beforeEach(function () {
    app(TenantContext::class)->forget();
});

test('the bot page renders the fixed model read-only', function () {
    $owner = provisionTenant(['ai_model' => 'gemini-2.5-flash-lite']);

    $response = $this->actingAs($owner)->get(route('bot.edit'));

    $response->assertOk();
    $response->assertSee('gemini-2.5-flash-lite');
});

test('updating bot settings saves system_prompt and temperature', function () {
    $owner = provisionTenant(['system_prompt' => 'قديم', 'temperature' => 30]);

    $response = $this->actingAs($owner)->put(route('bot.update'), [
        'system_prompt' => 'أنت مساعد لطيف يرد بالعربية.',
        'temperature' => 65,
    ]);

    $response->assertRedirect(route('bot.edit'));

    $account = WhatsappAccount::query()->firstOrFail();
    expect($account->system_prompt)->toBe('أنت مساعد لطيف يرد بالعربية.');
    expect($account->temperature)->toBe(65);
});

test('temperature is rejected when out of the 0..100 range', function () {
    $owner = provisionTenant();

    $response = $this->actingAs($owner)->put(route('bot.update'), [
        'system_prompt' => 'نص صالح',
        'temperature' => 150,
    ]);

    $response->assertSessionHasErrors('temperature');
});

test('system_prompt is required', function () {
    $owner = provisionTenant();

    $response = $this->actingAs($owner)->put(route('bot.update'), [
        'system_prompt' => '',
        'temperature' => 40,
    ]);

    $response->assertSessionHasErrors('system_prompt');
});
