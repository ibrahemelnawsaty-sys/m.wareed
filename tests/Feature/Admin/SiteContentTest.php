<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Settings\SiteSettings;
use App\Support\Tenancy\TenantContext;

/*
| Phase 4h — admin management of the PUBLIC site content (landing copy + SEO).
| Unlike the AI keys these are public copy, not secrets: the page renders the
| live values for editing, and they appear (escaped) on the landing page. A blank
| field reverts that field to its hard-coded default (§3). Admin-only (§1, §13);
| every printed value is escaped so raw HTML/script can never be injected (§13).
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
    app(SiteSettings::class)->forget();
});

test('a regular (non-admin) user is forbidden from the site content page (GET and PUT)', function () {
    $owner = User::factory()->create(); // ordinary owner with a tenant
    expect($owner->isAdmin())->toBeFalse();

    $this->actingAs($owner)->get(route('admin.site.edit'))->assertForbidden();
    $this->actingAs($owner)->put(route('admin.site.update'), [
        'hero_title' => 'should never be saved',
    ])->assertForbidden();

    // Nothing was written by the forbidden request.
    app(SiteSettings::class)->forget();
    expect(app(SiteSettings::class)->get('hero_title'))->toBeNull();
});

test('a super-admin can open the site content page', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)->get(route('admin.site.edit'))->assertOk();
});

test('the admin edits hero_title and it appears on the landing page', function () {
    $admin = makeAdmin();
    $custom = 'عنوان رئيسي مخصّص من الأدمن';

    $this->actingAs($admin)->put(route('admin.site.update'), [
        'hero_title' => $custom,
    ])->assertRedirect(route('admin.site.edit'));

    app(SiteSettings::class)->forget();
    expect(app(SiteSettings::class)->get('hero_title'))->toBe($custom);

    $this->get('/')->assertOk()->assertSee($custom);
});

test('the landing page falls back to defaults when nothing is set (does not break)', function () {
    // No site_settings rows at all — the page must still render its defaults.
    $this->get('/')
        ->assertOk()
        ->assertSee('وريد')                                   // default brand name
        ->assertSee('بوت واتساب ذكي يرد على عملائك تلقائياً', false); // default hero copy
});

test('seo_title and seo_description appear in the title tag and meta description', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)->put(route('admin.site.update'), [
        'seo_title' => 'عنوان SEO مخصّص',
        'seo_description' => 'وصف SEO مخصّص للصفحة الرئيسية.',
    ])->assertRedirect(route('admin.site.edit'));

    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('<title>عنوان SEO مخصّص</title>');
    expect($html)->toContain('name="description" content="وصف SEO مخصّص للصفحة الرئيسية."');
});

test('an announcement banner shows only when set', function () {
    // Not set → no banner.
    $this->get('/')->assertOk()->assertDontSee('عرض الإطلاق الخاص');

    $admin = makeAdmin();
    app(SiteSettings::class)->set('announcement', 'عرض الإطلاق الخاص');
    app(SiteSettings::class)->forget();

    $this->get('/')->assertOk()->assertSee('عرض الإطلاق الخاص');
});

test('a script tag stored in hero_title is escaped on the landing page (no HTML injection, §13)', function () {
    $admin = makeAdmin();
    $payload = '<script>alert(1)</script>';

    $this->actingAs($admin)->put(route('admin.site.update'), [
        'hero_title' => $payload,
    ])->assertRedirect(route('admin.site.edit'));

    $response = $this->get('/')->assertOk();

    // The raw, unescaped tag must NOT be present...
    $response->assertDontSee('<script>alert(1)</script>', false);
    // ...but the escaped form IS (proving the value rendered, safely).
    $response->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
});

test('a blank field reverts that field to its landing default (non-destructive, §3)', function () {
    $admin = makeAdmin();

    // Seed a custom hero title, then save the form with it blank.
    app(SiteSettings::class)->set('hero_title', 'قيمة سابقة');
    app(SiteSettings::class)->forget();

    $this->actingAs($admin)->put(route('admin.site.update'), [
        'hero_title' => '',
        'brand_name' => 'علامة جديدة',
    ])->assertRedirect(route('admin.site.edit'));

    app(SiteSettings::class)->forget();
    // Blank cleared the row → get() returns null → landing uses its default.
    expect(app(SiteSettings::class)->get('hero_title'))->toBeNull();
    expect(app(SiteSettings::class)->get('brand_name'))->toBe('علامة جديدة');
});

test('seo_title over 60 chars is rejected by validation', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->from(route('admin.site.edit'))
        ->put(route('admin.site.update'), [
            'seo_title' => str_repeat('ا', 61),
        ])
        ->assertSessionHasErrors('seo_title');
});
