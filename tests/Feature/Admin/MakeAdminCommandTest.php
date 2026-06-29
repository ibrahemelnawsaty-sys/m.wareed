<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
| The CLI is the only way a super-admin is born (§13). It must set is_admin via
| forceFill, give the admin no tenant, and verify the email.
*/

test('wareed:make-admin creates a verified super-admin with no tenant', function () {
    $this->artisan('wareed:make-admin', [
        'email' => 'owner@wareed.vip',
        'password' => 'super-secret-pass',
    ])->assertSuccessful();

    $admin = User::query()->withoutGlobalScopes()->where('email', 'owner@wareed.vip')->firstOrFail();

    expect($admin->is_admin)->toBeTrue();
    expect($admin->isAdmin())->toBeTrue();
    expect($admin->tenant_id)->toBeNull();
    expect($admin->role)->toBe('admin');
    expect($admin->email_verified_at)->not->toBeNull();
    expect(Hash::check('super-secret-pass', $admin->password))->toBeTrue();
});

test('wareed:make-admin refuses to clobber an existing email', function () {
    User::factory()->create(['email' => 'taken@wareed.vip']);

    $this->artisan('wareed:make-admin', [
        'email' => 'taken@wareed.vip',
        'password' => 'another-pass',
    ])->assertFailed();

    // Still exactly one user with that email, and it was not promoted.
    $rows = User::query()->withoutGlobalScopes()->where('email', 'taken@wareed.vip')->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->is_admin)->toBeFalse();
});
