<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\WhatsappAccount;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin HTTP client for the Meta WhatsApp Cloud API (ADR-01). Sends as the
 * customer's own number (`phone_number_id`).
 *
 * Token hygiene (§13): the access token is sent only in the Authorization
 * header, and on failure we NEVER let a token-bearing object reach a log. We do
 * not call ->throw() (its RequestException keeps the live Authorization: Bearer
 * request, which a reporter like Sentry/Flare would serialize) and we never set
 * `previous` to it — failures surface as a clean RuntimeException carrying only
 * the HTTP status + phone_number_id. This mirrors the AI-provider clients
 * (GeminiClient / OpenAiCompatibleClient). No silent swallowing (§3): the
 * caller reports the clean exception.
 */
class WhatsAppClient
{
    /**
     * Send a free-form text message to a recipient within the 24h window (§11).
     *
     * @return array{wa_message_id: ?string}
     *
     * @throws RuntimeException when the Cloud API call fails
     */
    public function sendText(WhatsappAccount $account, string $to, string $body): array
    {
        $response = $this->dispatch($account, 'text send', fn (): Response => $this->authed($account)
            ->asJson()
            ->post($this->messagesUrl($account), [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $body,
                ],
            ]));

        return $this->messageId($response);
    }

    /**
     * Send a pre-approved template message (works outside the 24h window, §11).
     * Reused by the connection wizard's test send and the templates phase.
     *
     * @param  array<int, mixed>  $components
     * @return array{wa_message_id: ?string}
     *
     * @throws RuntimeException when the Cloud API call fails
     */
    public function sendTemplate(
        WhatsappAccount $account,
        string $to,
        string $templateName,
        string $languageCode,
        array $components = [],
    ): array {
        $template = [
            'name' => $templateName,
            'language' => ['code' => $languageCode],
        ];

        // Omit `components` entirely when empty: the default hello_world template
        // has no parameters and Meta rejects an empty components array on some.
        if ($components !== []) {
            $template['components'] = $components;
        }

        $response = $this->dispatch($account, 'template send', fn (): Response => $this->authed($account)
            ->asJson()
            ->post($this->messagesUrl($account), [
                'messaging_product' => 'whatsapp',
                'to' => $to,
                'type' => 'template',
                'template' => $template,
            ]));

        return $this->messageId($response);
    }

    /**
     * Probe Meta to confirm the phone number id + access token are valid and
     * surface the number's live status (verified name, quality rating, etc.).
     * Used by the connection wizard before the owner goes live (§10).
     *
     * @return array{
     *     verified_name: string,
     *     display_phone_number: string,
     *     quality_rating: string,
     *     code_verification_status: string,
     * }
     *
     * @throws RuntimeException when the Cloud API call fails
     */
    public function verifyConnection(WhatsappAccount $account): array
    {
        $base = rtrim((string) config('services.whatsapp.graph_base'), '/');
        $version = (string) config('services.whatsapp.api_version');
        $url = "{$base}/{$version}/{$account->phone_number_id}";

        $response = $this->dispatch($account, 'connection check', fn (): Response => $this->authed($account)
            ->get($url, [
                'fields' => 'verified_name,display_phone_number,quality_rating,code_verification_status',
            ]));

        return [
            'verified_name' => $this->stringField($response->json('verified_name')),
            'display_phone_number' => $this->stringField($response->json('display_phone_number')),
            'quality_rating' => $this->stringField($response->json('quality_rating')),
            'code_verification_status' => $this->stringField($response->json('code_verification_status')),
        ];
    }

    /**
     * Run a Cloud API request and convert any failure into a clean, token-free
     * RuntimeException (§13). A non-2xx response is detected via failed() rather
     * than ->throw(), so the token-bearing RequestException is never created;
     * a transport failure is the only thing that throws, and ConnectionException
     * carries no request object — but we still pass NO `previous` for strictness.
     *
     * @param  Closure(): Response  $send
     *
     * @throws RuntimeException
     */
    private function dispatch(WhatsappAccount $account, string $action, Closure $send): Response
    {
        try {
            $response = $send();
        } catch (ConnectionException) {
            throw new RuntimeException(
                "WhatsApp {$action} could not reach Meta for phone_number_id {$account->phone_number_id}.",
            );
        }

        if ($response->failed()) {
            // Status + phone_number_id only — never the token, never the request.
            throw new RuntimeException(
                "WhatsApp {$action} failed with status {$response->status()} for phone_number_id {$account->phone_number_id}.",
            );
        }

        return $response;
    }

    private function authed(WhatsappAccount $account): PendingRequest
    {
        return Http::withToken((string) $account->access_token)->acceptJson();
    }

    private function messagesUrl(WhatsappAccount $account): string
    {
        $base = rtrim((string) config('services.whatsapp.graph_base'), '/');
        $version = (string) config('services.whatsapp.api_version');

        return "{$base}/{$version}/{$account->phone_number_id}/messages";
    }

    /**
     * @return array{wa_message_id: ?string}
     */
    private function messageId(Response $response): array
    {
        /** @var mixed $waMessageId */
        $waMessageId = $response->json('messages.0.id');

        return [
            'wa_message_id' => is_string($waMessageId) ? $waMessageId : null,
        ];
    }

    /**
     * Coerce a Graph field to a safe string. Meta may omit a field (null) or
     * return a non-string; we never trust the shape — keep it a plain string.
     */
    private function stringField(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
