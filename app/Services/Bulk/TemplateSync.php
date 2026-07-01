<?php

declare(strict_types=1);

namespace App\Services\Bulk;

use App\Models\MessageTemplate;
use App\Models\WhatsappAccount;
use App\Services\WhatsApp\WhatsAppClient;

/**
 * Mirrors a WABA's Meta-approved templates into our cache (Phase 7c, §11). Meta is
 * the source of truth for a template's `status`/`category`/body — this service is
 * the only writer of those mirror fields, refreshing them so the bulk form offers
 * only currently-`approved` templates and rejects everything else.
 *
 * Runs INSIDE a bound tenant context (controller has bound the tenant), so every
 * read/write is TenantScope-filtered (§1).
 *
 * Non-destructive (§3): a template that vanished from Meta is NOT deleted — the
 * owner's historical campaigns still reference it, and a transient Meta hiccup must
 * never wipe the cache. The upsert only ever updates what Meta currently returns.
 * Untrusted input (§13): Meta's payload is normalised by WhatsAppClient::listTemplates
 * and written via the model's trusted syncFromMeta()/forceFill, never mass assignment,
 * so a Meta response can never flip a field we don't intend (e.g. tenant_id).
 */
class TemplateSync
{
    public function __construct(private readonly WhatsAppClient $client) {}

    /**
     * Pull the account's templates from Meta and upsert each on
     * (whatsapp_account_id, name, language). Returns how many templates were synced.
     *
     * @throws \RuntimeException when the Cloud API call fails (surfaced gently by
     *                           the controller; never the token, §13)
     */
    public function sync(WhatsappAccount $account): int
    {
        $templates = $this->client->listTemplates($account);

        $synced = 0;

        foreach ($templates as $template) {
            $name = $template['name'];
            $language = $template['language'];

            // A template with no name/language is unusable — skip it rather than
            // create a junk row keyed on empty strings.
            if ($name === '' || $language === '') {
                continue;
            }

            $bodyText = $this->extractBodyText($template['components']);
            $variableCount = $this->countVariables($bodyText);
            $status = $this->normaliseStatus($template['status']);
            $category = $this->normaliseCategory($template['category']);

            // firstOrCreate on the trusted, server-known key — never from request
            // input. The owner-authored descriptors are fillable; the Meta-mirror
            // fields are then written through the model's trusted syncFromMeta (§13).
            $model = MessageTemplate::query()->firstOrCreate(
                [
                    'whatsapp_account_id' => $account->id,
                    'name' => $name,
                    'language' => $language,
                ],
                [
                    'category' => $category,
                    'body_text' => $bodyText,
                ],
            );

            $model->syncFromMeta($status, $category, $variableCount, $bodyText);

            $synced++;
        }

        return $synced;
    }

    /**
     * Pull the BODY component's text out of Meta's `components` array. Meta returns
     * a list of components ({type: BODY|HEADER|FOOTER|BUTTONS, text?: ...}); we want
     * the BODY text (the message the contact sees, with {{n}} placeholders). Untrusted
     * shape — anything unexpected yields null.
     *
     * @param  array<int, mixed>  $components
     */
    private function extractBodyText(array $components): ?string
    {
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            /** @var mixed $type */
            $type = $component['type'] ?? null;

            if (is_string($type) && strtoupper($type) === 'BODY') {
                /** @var mixed $text */
                $text = $component['text'] ?? null;

                return is_string($text) && $text !== '' ? $text : null;
            }
        }

        return null;
    }

    /**
     * Count the distinct {{n}} positional placeholders in the body text — the
     * number of variables a campaign must supply for this template (Meta rejects a
     * mismatch). Counts the highest index referenced so {{1}}…{{3}} ⇒ 3 even if a
     * middle one is repeated; a body with no placeholders ⇒ 0.
     */
    private function countVariables(?string $bodyText): int
    {
        if ($bodyText === null || $bodyText === '') {
            return 0;
        }

        if (preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $bodyText, $matches) === false) {
            return 0;
        }

        $indexes = array_map('intval', $matches[1]);

        return $indexes === [] ? 0 : max($indexes);
    }

    /**
     * Map Meta's status onto our known set, defaulting unknown values to 'unknown'
     * so a surprise Meta string never makes a template appear sendable.
     */
    private function normaliseStatus(string $status): string
    {
        $known = [
            MessageTemplate::STATUS_APPROVED,
            MessageTemplate::STATUS_PENDING,
            MessageTemplate::STATUS_REJECTED,
            MessageTemplate::STATUS_PAUSED,
            MessageTemplate::STATUS_DISABLED,
        ];

        $lower = strtolower($status);

        return in_array($lower, $known, true) ? $lower : MessageTemplate::STATUS_UNKNOWN;
    }

    /**
     * Map Meta's category onto our known set, defaulting to 'utility' (the most
     * restrictive/neutral) for an unrecognised value.
     */
    private function normaliseCategory(string $category): string
    {
        $known = ['marketing', 'utility', 'authentication'];

        $lower = strtolower($category);

        return in_array($lower, $known, true) ? $lower : 'utility';
    }
}
