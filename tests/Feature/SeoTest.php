<?php

declare(strict_types=1);

use App\Support\Tenancy\TenantContext;

/*
| Phase 4h — dynamic SEO endpoints (§11). The sitemap lists the public pages;
| robots.txt allows indexing in PRODUCTION ONLY and disallows everything else so
| staging/preview is never indexed (§11). Both are public and unauthenticated.
*/

beforeEach(function () {
    app(TenantContext::class)->forget();
});

test('GET /sitemap.xml returns valid XML listing the home page with an xml content type', function () {
    $response = $this->get('/sitemap.xml');

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml');

    $body = $response->getContent();
    expect($body)->toContain('<?xml version="1.0"');
    expect($body)->toContain('<urlset');
    // The home page URL must be listed (trailing slash, matching the canonical).
    expect($body)->toContain('<loc>'.rtrim(config('app.url'), '/').'/</loc>');
    // login + register are listed too.
    expect($body)->toContain(route('login'));
    expect($body)->toContain(route('register'));
});

test('GET /robots.txt disallows everything in a non-production environment (§11)', function () {
    // The test suite runs with APP_ENV=testing (non-production).
    expect(app()->environment('production'))->toBeFalse();

    $response = $this->get('/robots.txt');

    $response->assertOk();
    // text/plain (Laravel appends the charset to text/* responses, which is fine).
    expect($response->headers->get('Content-Type'))->toStartWith('text/plain');
    expect($response->getContent())->toContain('Disallow: /');
    expect($response->getContent())->not->toContain('Allow: /');
});

test('GET /robots.txt allows indexing and advertises the sitemap in production (§11)', function () {
    // Force the production environment for this request only.
    app()->detectEnvironment(fn () => 'production');
    expect(app()->environment('production'))->toBeTrue();

    $response = $this->get('/robots.txt');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toStartWith('text/plain');
    $body = $response->getContent();
    expect($body)->toContain('User-agent: *');
    expect($body)->toContain('Allow: /');
    expect($body)->toContain('Sitemap: '.rtrim(config('app.url'), '/').'/sitemap.xml');
    expect($body)->not->toContain('Disallow: /');
});

test('the landing page is noindex outside production and index in production (§11)', function () {
    // Non-production (testing): noindex.
    $this->get('/')->assertOk()->assertSee('content="noindex,nofollow"', false);

    // Production: index,follow.
    app()->detectEnvironment(fn () => 'production');
    $this->get('/')->assertOk()->assertSee('content="index,follow"', false);
});

test('the landing page emits schema.org JSON-LD structured data', function () {
    $html = $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('application/ld+json');
    expect($html)->toContain('"@context":"https://schema.org"');
    expect($html)->toContain('"Organization"');
    expect($html)->toContain('"WebSite"');
});
