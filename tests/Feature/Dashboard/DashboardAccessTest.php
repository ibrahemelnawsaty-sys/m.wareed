<?php

declare(strict_types=1);

use App\Support\Tenancy\TenantContext;

beforeEach(function () {
    app(TenantContext::class)->forget();
});

test('guests are redirected to login from every dashboard route', function (string $method, string $routeName) {
    $url = route($routeName);

    $response = match ($method) {
        'get' => $this->get($url),
        'put' => $this->put($url),
        'post' => $this->post($url),
    };

    $response->assertRedirect(route('login'));
})->with([
    ['get', 'dashboard'],
    ['get', 'whatsapp.edit'],
    ['put', 'whatsapp.update'],
    ['get', 'bot.edit'],
    ['put', 'bot.update'],
    ['get', 'knowledge.index'],
    ['get', 'knowledge.create'],
    ['post', 'knowledge.store'],
    ['get', 'conversations.index'],
    ['get', 'analytics.index'],
    ['get', 'playground.index'],
    ['post', 'playground.send'],
]);

test('an authenticated tenant owner can reach the dashboard', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)->get(route('dashboard'))->assertOk();
});
