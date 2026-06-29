<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use App\Models\WhatsappAccount;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Provision a tenant with an owner user and a WhatsApp account, mirroring the
 * onboarding flow, then bind that tenant for the running test (so TenantScope
 * filters to it). Returns the owner user.
 *
 * @param  array<string, mixed>  $accountAttributes
 */
function provisionTenant(array $accountAttributes = []): User
{
    $tenant = Tenant::factory()->create();

    $context = app(TenantContext::class);
    // Bind explicitly (not via run()) so the tenant stays bound for the rest of
    // the test body and its assertions.
    $context->set($tenant->id);

    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);

    WhatsappAccount::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
    ], $accountAttributes));

    return $user;
}

/**
 * A genuine platform super-admin: no tenant, with is_admin set via forceFill
 * (never mass assignment, §13) — exactly how the make-admin command provisions
 * one. Shared across the Admin feature tests.
 */
function makeAdmin(): User
{
    $admin = User::factory()->create(['tenant_id' => null]);
    $admin->forceFill(['is_admin' => true])->save();

    return $admin;
}
