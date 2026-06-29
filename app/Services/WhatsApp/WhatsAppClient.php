<?php

declare(strict_types=1);

namespace App\Services\WhatsApp;

use App\Models\WhatsappAccount;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Thin HTTP client for sending WhatsApp messages via Meta Cloud API (ADR-01).
 * Sends as the customer's own number (`phone_number_id`). The bearer token is
 * never logged (§13); failures are reported and surfaced as explicit
 * exceptions — no silent swallowing (§3).
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
        $base = rtrim((string) config('services.whatsapp.graph_base'), '/');
        $version = (string) config('services.whatsapp.api_version');
        $url = "{$base}/{$version}/{$account->phone_number_id}/messages";

        try {
            $response = Http::withToken((string) $account->access_token)
                ->asJson()
                ->acceptJson()
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => false,
                        'body' => $body,
                    ],
                ])
                ->throw();
        } catch (Throwable $e) {
            // Never include the token; identify by phone_number_id only (§13).
            report($e);

            throw new RuntimeException(
                "WhatsApp send failed for phone_number_id {$account->phone_number_id}.",
                previous: $e,
            );
        }

        /** @var mixed $waMessageId */
        $waMessageId = $response->json('messages.0.id');

        return [
            'wa_message_id' => is_string($waMessageId) ? $waMessageId : null,
        ];
    }
}
