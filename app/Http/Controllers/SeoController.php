<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * Dynamic SEO endpoints for search-engine discovery (§11): the XML sitemap and
 * robots.txt. Both are PUBLIC, unauthenticated, and read-only.
 *
 * The cornerstone rule (§11): only PRODUCTION may be indexed. Any non-production
 * environment (local, staging, testing) returns `Disallow: /` so Google never
 * indexes a preview of the platform — protecting the live site's ranking from
 * duplicate/staging content.
 */
class SeoController extends Controller
{
    /**
     * The crawlable public URLs. Kept small and explicit (marketing + auth entry
     * points) — never tenant-scoped or authenticated pages.
     *
     * @return list<string>
     */
    private function urls(): array
    {
        // The home URL carries a trailing slash to match the landing page's
        // <link rel="canonical"> exactly (no canonical/sitemap mismatch).
        return [
            rtrim(config('app.url'), '/').'/',
            route('login'),
            route('register'),
        ];
    }

    /**
     * GET /sitemap.xml — a valid urlset listing the public pages. Every URL is
     * XML-escaped via htmlspecialchars so no value can break the document.
     */
    public function sitemap(): Response
    {
        $now = now()->toAtomString();

        $entries = '';
        foreach ($this->urls() as $url) {
            $loc = htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $entries .= "  <url>\n"
                ."    <loc>{$loc}</loc>\n"
                ."    <lastmod>{$now}</lastmod>\n"
                ."  </url>\n";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .$entries
            .'</urlset>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    /**
     * GET /robots.txt — allow indexing in PRODUCTION ONLY (§11). Every other
     * environment disallows everything so staging/preview is never indexed.
     */
    public function robots(): Response
    {
        if (app()->environment('production')) {
            $sitemap = url('/sitemap.xml');
            $body = "User-agent: *\n"
                ."Allow: /\n"
                ."Sitemap: {$sitemap}\n";
        } else {
            // Non-production: keep search engines out entirely (§11).
            $body = "User-agent: *\n"
                ."Disallow: /\n";
        }

        return response($body, 200, ['Content-Type' => 'text/plain']);
    }
}
