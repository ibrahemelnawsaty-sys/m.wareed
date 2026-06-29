<?php

declare(strict_types=1);

use App\Models\User;

/*
| The super-admin area crosses all tenants and is the one deliberate exception
| to tenant isolation (§1, §13). These tests lock the gate: only is_admin users
| get in, everyone else is forbidden.
*/

test('a regular (non-admin) user is forbidden from the admin dashboard', function () {
    // A normal owner with a tenant — the common case.
    $user = User::factory()->create();
    expect($user->isAdmin())->toBeFalse();

    $this->actingAs($user)->get('/admin')->assertForbidden();
});

test('a super-admin can reach the admin dashboard', function () {
    // Admins have no tenant; the route group is intentionally tenant-free.
    $admin = User::factory()->create(['tenant_id' => null]);
    $admin->forceFill(['is_admin' => true])->save();

    $this->actingAs($admin)->get('/admin')->assertOk();
});

test('a guest is redirected to login, not shown the admin dashboard', function () {
    $this->get('/admin')->assertRedirect('/login');
});
