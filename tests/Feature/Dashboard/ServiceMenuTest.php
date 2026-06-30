<?php

declare(strict_types=1);

use App\Models\ServiceMenu;
use App\Models\User;
use App\Support\Tenancy\TenantContext;

beforeEach(function () {
    app(TenantContext::class)->forget();
});

test('the owner can open the service menu editor', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)->get(route('menu.edit'))->assertOk();
});

test('an agent is forbidden from the service menu editor', function () {
    app(TenantContext::class)->forget();
    $owner = provisionTenant();
    $agent = User::factory()->create([
        'tenant_id' => $owner->tenant_id,
        'role' => 'agent',
        'email_verified_at' => now(),
    ]);
    app(TenantContext::class)->forget();

    $this->actingAs($agent)->get(route('menu.edit'))->assertForbidden();
    $this->actingAs($agent)->put(route('menu.update'), [])->assertForbidden();
});

test('the owner saves a menu with reply and handoff rows', function () {
    $owner = provisionTenant();

    $response = $this->actingAs($owner)->put(route('menu.update'), [
        'enabled' => 1,
        'trigger_on_welcome' => 1,
        'header' => 'مرحباً',
        'body' => 'اختر خدمة من القائمة',
        'button_label' => 'الخدمات',
        'footer' => 'نسعد بخدمتك',
        'rows' => [
            ['title' => 'ساعات العمل', 'description' => 'من 9 إلى 5', 'action_type' => 'reply', 'reply_text' => 'نعمل من 9 صباحاً حتى 5 مساءً.'],
            ['title' => 'تحدث مع موظف', 'action_type' => 'handoff'],
        ],
    ]);

    $response->assertRedirect(route('menu.edit'));

    $menu = ServiceMenu::query()->firstOrFail();
    expect($menu->enabled)->toBeTrue()
        ->and($menu->trigger_on_welcome)->toBeTrue()
        ->and($menu->body)->toBe('اختر خدمة من القائمة');

    $rows = $menu->rows()->orderBy('sort_order')->get();
    expect($rows)->toHaveCount(2)
        // row_key is generated server-side, never from input.
        ->and($rows[0]->row_key)->toBe('row_0')
        ->and($rows[0]->action_type)->toBe('reply')
        ->and($rows[0]->reply_text)->toBe('نعمل من 9 صباحاً حتى 5 مساءً.')
        ->and($rows[1]->row_key)->toBe('row_1')
        ->and($rows[1]->action_type)->toBe('handoff')
        // handoff row never keeps a reply_text.
        ->and($rows[1]->reply_text)->toBeNull();
});

test('the row sync is a full rebuild, not append', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)->put(route('menu.update'), [
        'enabled' => 1,
        'body' => 'اختر',
        'button_label' => 'القائمة',
        'rows' => [
            ['title' => 'أ', 'action_type' => 'reply', 'reply_text' => 'ردّ أ'],
            ['title' => 'ب', 'action_type' => 'reply', 'reply_text' => 'ردّ ب'],
        ],
    ])->assertRedirect();

    // Second save with a single row replaces the set (no stale rows left).
    $this->actingAs($owner)->put(route('menu.update'), [
        'enabled' => 1,
        'body' => 'اختر',
        'button_label' => 'القائمة',
        'rows' => [
            ['title' => 'ج', 'action_type' => 'handoff'],
        ],
    ])->assertRedirect();

    $menu = ServiceMenu::query()->firstOrFail();
    $rows = $menu->rows()->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->title)->toBe('ج')
        ->and($rows[0]->row_key)->toBe('row_0');
});

test('more than 10 rows is rejected', function () {
    $owner = provisionTenant();

    $rows = [];
    for ($i = 0; $i < 11; $i++) {
        $rows[] = ['title' => 'صف '.$i, 'action_type' => 'reply', 'reply_text' => 'نص'];
    }

    $this->actingAs($owner)->put(route('menu.update'), [
        'enabled' => 1,
        'body' => 'اختر',
        'button_label' => 'القائمة',
        'rows' => $rows,
    ])->assertSessionHasErrors('rows');
});

test('a row title longer than 24 characters is rejected', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)->put(route('menu.update'), [
        'enabled' => 1,
        'body' => 'اختر',
        'button_label' => 'القائمة',
        'rows' => [
            ['title' => str_repeat('a', 25), 'action_type' => 'reply', 'reply_text' => 'نص'],
        ],
    ])->assertSessionHasErrors('rows.0.title');
});

test('a reply row without reply_text is rejected', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)->put(route('menu.update'), [
        'enabled' => 1,
        'body' => 'اختر',
        'button_label' => 'القائمة',
        'rows' => [
            ['title' => 'خدمة', 'action_type' => 'reply'],
        ],
    ])->assertSessionHasErrors('rows.0.reply_text');
});

test('the button label longer than 20 characters is rejected', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)->put(route('menu.update'), [
        'enabled' => 1,
        'body' => 'اختر',
        'button_label' => str_repeat('a', 21),
        'rows' => [],
    ])->assertSessionHasErrors('button_label');
});

test('a menu can be saved with no rows', function () {
    $owner = provisionTenant();

    $this->actingAs($owner)->put(route('menu.update'), [
        'body' => 'لا قائمة بعد',
        'button_label' => 'القائمة',
        'rows' => [],
    ])->assertRedirect(route('menu.edit'));

    expect(ServiceMenu::query()->firstOrFail()->rows()->count())->toBe(0);
});
