<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\UsageCounter;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

beforeEach(function () {
    app(TenantContext::class)->forget();
});

test('analytics shows only the current tenant aggregates', function () {
    $owner = provisionTenant();
    $account = WhatsappAccount::query()->firstOrFail();
    $tenantId = $account->tenant_id;

    // This tenant's usage: today + a day inside the 30-day window.
    UsageCounter::factory()->create([
        'tenant_id' => $tenantId,
        'date' => Carbon::today()->toDateString(),
        'messages' => 7,
        'tokens_in' => 1000,
        'tokens_out' => 500,
        'cost_micros' => 250000,
    ]);
    UsageCounter::factory()->create([
        'tenant_id' => $tenantId,
        'date' => Carbon::today()->subDays(5)->toDateString(),
        'messages' => 3,
        'tokens_in' => 200,
        'tokens_out' => 100,
        'cost_micros' => 50000,
    ]);

    // Another tenant's usage — must never bleed into A's totals.
    $tenantB = Tenant::factory()->create();
    UsageCounter::factory()->create([
        'tenant_id' => $tenantB->id,
        'date' => Carbon::today()->toDateString(),
        'messages' => 9999,
        'tokens_in' => 9_000_000,
        'tokens_out' => 9_000_000,
        'cost_micros' => 9_000_000,
    ]);

    $response = $this->actingAs($owner)->get(route('analytics.index'))->assertOk();

    // Today = 7 (not 7 + B's 9999). 30-day messages = 7 + 3 = 10.
    // 30-day tokens = (1000+500) + (200+100) = 1800.
    // 30-day cost = 250000 + 50000 = 300000 micros = $0.30.
    $response->assertSee('10')      // 30-day messages
        ->assertSee('1,800')        // total tokens
        ->assertSee('$0.30')        // cost (display-only conversion)
        ->assertDontSee('9,999')    // tenant B leaked nothing
        ->assertDontSee('9,000,000');
});

test('analytics renders cleanly with no usage data', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)->get(route('analytics.index'))
        ->assertOk()
        ->assertSee('$0.00'); // null-safe cost component
});
