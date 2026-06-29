<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * A plain-text-into-HTML notification an admin sends to a customer (§13).
 *
 * Sent SYNCHRONOUSLY (does NOT implement ShouldQueue) — there is no persistent
 * worker on shared hosting (ADR-03, §14), and an admin clicking "send" expects
 * an immediate, surfaced success/failure rather than a silently queued job.
 *
 * SECURITY: `subject` and `bodyText` come from admin input. The Blade view
 * escapes both via {{ }} (never {!! !!}), so no HTML/markup the admin types can
 * be injected into the rendered email — the body renders as escaped text with
 * newlines preserved by CSS, not by raw HTML.
 */
class CustomerNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $subjectLine,
        public string $bodyText,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.customer-notification',
            with: [
                'subjectLine' => $this->subjectLine,
                'bodyText' => $this->bodyText,
            ],
        );
    }
}
