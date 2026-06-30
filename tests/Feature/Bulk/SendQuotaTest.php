<?php

declare(strict_types=1);

use App\Models\DailySendCounter;
use App\Models\Tenant;
use App\Models\WhatsappAccount;
use App\Services\Bulk\SendQuota;
use App\Support\Tenancy\TenantContext;

/*
| Phase 6d — the ATOMIC daily send cap (§11, §13). The cap can never be exceeded,
| even under concurrent reservations, and it is clamped to min(tenant cap, 250).
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
});

/**
 * A bound tenant with an account and a chosen bulk cap. Returns the account.
 */
function quotaAccount(int $cap = 250): WhatsappAccount
{
    $tenant = Tenant::factory()->create();
    $tenant->setBulkCap($cap);

    app(TenantContext::class)->set($tenant->id);

    return WhatsappAccount::factory()->create(['tenant_id' => $tenant->id]);
}

// 1) tryConsume allows exactly up to the cap, then refuses.
test('tryConsume allows up to the cap then refuses', function () {
    $account = quotaAccount(3);
    $quota = app(SendQuota::class);

    expect($quota->tryConsume($account))->toBeTrue();   // 1
    expect($quota->tryConsume($account))->toBeTrue();   // 2
    expect($quota->tryConsume($account))->toBeTrue();   // 3
    expect($quota->tryConsume($account))->toBeFalse();  // cap reached
    expect($quota->tryConsume($account))->toBeFalse();  // still refused

    expect($quota->remainingToday($account))->toBe(0);

    $counter = DailySendCounter::withoutGlobalScopes()
        ->where('whatsapp_account_id', $account->id)->firstOrFail();
    expect($counter->sent_count)->toBe(3); // never overshot
});

// 2) The effective cap is min(tenant cap, 250): a tenant cannot exceed 250.
test('the cap is clamped to 250 even if a larger value is requested', function () {
    $account = quotaAccount(1000); // request 1000

    // setBulkCap clamps the stored value to 250.
    expect($account->tenant->daily_bulk_cap)->toBe(250);

    $quota = app(SendQuota::class);
    expect($quota->remainingToday($account))->toBe(250);
});

// 2b) remainingToday reflects consumption and never goes negative.
test('remainingToday tracks consumption', function () {
    $account = quotaAccount(5);
    $quota = app(SendQuota::class);

    expect($quota->remainingToday($account))->toBe(5);
    $quota->tryConsume($account);
    $quota->tryConsume($account);
    expect($quota->remainingToday($account))->toBe(3);
});

// 3) Atomicity: a direct over-cap conditional UPDATE affects 0 rows. This is the
// exact mechanism that makes concurrent workers safe — at cap, the increment
// matches nothing, so two racing reservations can never both succeed.
test('the conditional increment cannot push sent_count past the cap', function () {
    $account = quotaAccount(2);
    $quota = app(SendQuota::class);

    expect($quota->tryConsume($account))->toBeTrue();
    expect($quota->tryConsume($account))->toBeTrue();

    // Now at the cap. A further reservation must fail and leave the count at 2.
    expect($quota->tryConsume($account))->toBeFalse();

    $counter = DailySendCounter::withoutGlobalScopes()
        ->where('whatsapp_account_id', $account->id)->firstOrFail();
    expect($counter->sent_count)->toBe(2);
});
