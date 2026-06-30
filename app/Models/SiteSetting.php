<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single PUBLIC site-content entry (key/value), managed by the super-admin
 * (Phase 4h). The store for the editable landing-page copy and its SEO metadata
 * (hero text, brand/contact details, seo_title/description/keywords, …).
 *
 * UNLIKE {@see Setting}, this value is PUBLIC marketing copy — it is meant to be
 * seen by every visitor — so it is stored as plaintext: no `encrypted` cast and
 * no `$hidden`. NEVER store a secret here (that belongs in {@see Setting}, §13).
 *
 * Not a tenant model: site content is global to the platform, so there is no
 * tenant_id and no TenantScope here.
 */
class SiteSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
    ];
}
