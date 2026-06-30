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

/*
| Phase 4h+ — admin-editable FEATURES grid (#features) and FAQ accordion (#faq),
| stored as JSON lists in SiteSettings. The admin edits text only (feature icons
| stay fixed by index). Unset/blank/corrupt JSON falls back to the hard-coded
| defaults (§3, never a 500); every value is escaped on the landing page (§13);
| the editor is admin-only; the row counts are bounded (8 features / 12 FAQ).
*/

test('an edited feature title appears on the landing page', function () {
    $admin = makeAdmin();
    $custom = 'ميزة مخصّصة من الأدمن';

    $this->actingAs($admin)->put(route('admin.site.update'), [
        'features' => [
            ['title' => $custom, 'description' => 'وصف الميزة المخصّصة.'],
        ],
    ])->assertRedirect(route('admin.site.edit'));

    app(SiteSettings::class)->forget();

    $this->get('/')->assertOk()
        ->assertSee($custom)
        ->assertSee('وصف الميزة المخصّصة.');
});

test('an edited FAQ question appears on the landing page', function () {
    $admin = makeAdmin();
    $question = 'سؤال مخصّص من الأدمن؟';
    $answer = 'إجابة مخصّصة على السؤال.';

    $this->actingAs($admin)->put(route('admin.site.update'), [
        'faq' => [
            ['question' => $question, 'answer' => $answer],
        ],
    ])->assertRedirect(route('admin.site.edit'));

    app(SiteSettings::class)->forget();

    $this->get('/')->assertOk()
        ->assertSee($question)
        ->assertSee($answer);
});

test('a non-admin cannot edit features or faq (PUT forbidden, nothing written)', function () {
    $owner = User::factory()->create();
    expect($owner->isAdmin())->toBeFalse();

    $this->actingAs($owner)->put(route('admin.site.update'), [
        'features' => [['title' => 'يجب ألا يُحفظ', 'description' => '']],
        'faq' => [['question' => 'يجب ألا يُحفظ؟', 'answer' => '']],
    ])->assertForbidden();

    app(SiteSettings::class)->forget();
    expect(app(SiteSettings::class)->get('features'))->toBeNull();
    expect(app(SiteSettings::class)->get('faq'))->toBeNull();
});

test('when features/faq are unset the landing page shows the defaults', function () {
    // No rows stored at all — the default copy must still render.
    $this->get('/')->assertOk()
        ->assertSee('رد آلي ذكي')               // default first feature
        ->assertSee('هل أحتاج خبرة تقنية؟');     // default first FAQ question
});

test('corrupt features/faq JSON falls back to defaults without a 500 (§3)', function () {
    // Write malformed JSON straight to the store, bypassing the controller.
    app(SiteSettings::class)->set('features', '{not valid json');
    app(SiteSettings::class)->set('faq', '[1, 2, 3]'); // valid JSON, wrong shape
    app(SiteSettings::class)->forget();

    $this->get('/')->assertOk()
        ->assertSee('رد آلي ذكي')               // default feature still rendered
        ->assertSee('هل أحتاج خبرة تقنية؟');     // default FAQ still rendered
});

test('a script tag in a feature title is escaped on the landing page (§13)', function () {
    $admin = makeAdmin();
    $payload = '<script>alert(2)</script>';

    $this->actingAs($admin)->put(route('admin.site.update'), [
        'features' => [
            ['title' => $payload, 'description' => 'وصف.'],
        ],
    ])->assertRedirect(route('admin.site.edit'));

    app(SiteSettings::class)->forget();

    $response = $this->get('/')->assertOk();
    $response->assertDontSee('<script>alert(2)</script>', false);
    $response->assertSee('&lt;script&gt;alert(2)&lt;/script&gt;', false);
});

test('the 8-feature limit is enforced by validation', function () {
    $admin = makeAdmin();

    $rows = [];
    for ($i = 0; $i < 9; $i++) {
        $rows[] = ['title' => 'ميزة رقم '.$i, 'description' => ''];
    }

    $this->actingAs($admin)
        ->from(route('admin.site.edit'))
        ->put(route('admin.site.update'), ['features' => $rows])
        ->assertSessionHasErrors('features');

    app(SiteSettings::class)->forget();
    expect(app(SiteSettings::class)->get('features'))->toBeNull();
});

test('the 12-faq limit is enforced by validation', function () {
    $admin = makeAdmin();

    $rows = [];
    for ($i = 0; $i < 13; $i++) {
        $rows[] = ['question' => 'سؤال رقم '.$i.'؟', 'answer' => ''];
    }

    $this->actingAs($admin)
        ->from(route('admin.site.edit'))
        ->put(route('admin.site.update'), ['faq' => $rows])
        ->assertSessionHasErrors('faq');
});

test('blank repeater rows are dropped and an all-blank list reverts to default (§3)', function () {
    $admin = makeAdmin();

    // Seed a custom feature, then submit a list whose only row is blank.
    app(SiteSettings::class)->set('features', json_encode([
        ['title' => 'قيمة سابقة', 'description' => 'وصف سابق'],
    ], JSON_UNESCAPED_UNICODE));
    app(SiteSettings::class)->forget();

    $this->actingAs($admin)->put(route('admin.site.update'), [
        'features' => [
            ['title' => '', 'description' => ''],   // fully blank → dropped
            ['title' => 'ميزة باقية', 'description' => ''],
        ],
    ])->assertRedirect(route('admin.site.edit'));

    app(SiteSettings::class)->forget();
    $stored = json_decode((string) app(SiteSettings::class)->get('features'), true);

    expect($stored)->toHaveCount(1);
    expect($stored[0]['title'])->toBe('ميزة باقية');

    // Now submit an all-blank list → stored as null → landing default returns.
    $this->actingAs($admin)->put(route('admin.site.update'), [
        'features' => [['title' => '', 'description' => '']],
    ])->assertRedirect(route('admin.site.edit'));

    app(SiteSettings::class)->forget();
    expect(app(SiteSettings::class)->get('features'))->toBeNull();
    $this->get('/')->assertOk()->assertSee('رد آلي ذكي'); // default restored
});

test('a feature description longer than 240 chars is rejected', function () {
    $admin = makeAdmin();

    $this->actingAs($admin)
        ->from(route('admin.site.edit'))
        ->put(route('admin.site.update'), [
            'features' => [
                ['title' => 'عنوان صالح', 'description' => str_repeat('ا', 241)],
            ],
        ])
        ->assertSessionHasErrors('features.0.description');
});
