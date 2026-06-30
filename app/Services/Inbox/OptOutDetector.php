<?php

declare(strict_types=1);

namespace App\Services\Inbox;

/**
 * Detects, from an inbound customer message, whether the customer is asking to
 * UNSUBSCRIBE from bulk messaging (Meta opt-out, §11). A pure, side-effect-free
 * keyword test — cheap to run on every inbound message and trivially testable,
 * mirroring {@see HandoffDetector}.
 *
 * Matching is deliberate (no NLP) — lightweight on shared hosting and
 * predictable. Crucially it is tuned AGAINST false positives, because an opt-out
 * is permanent and silently removes a legitimate, opted-in contact from every
 * future campaign: long explicit phrases match anywhere, but short ambiguous
 * tokens ('stop', 'إيقاف') match only when they are the WHOLE message — the
 * "reply STOP to unsubscribe" convention — so an ordinary message that merely
 * contains the word ("bus stop") is never mistaken for an unsubscribe.
 */
class OptOutDetector
{
    /**
     * Unambiguous unsubscribe phrases — matched by containment anywhere in the
     * message. All lowercase.
     *
     * @var list<string>
     */
    private const PHRASES = [
        // Arabic
        'الغاء الاشتراك',
        'إلغاء الاشتراك',
        'الغاء الإشتراك',
        'إلغاء الإشتراك',
        'إلغاء اشتراك',
        'الغاء اشتراك',
        // English
        'unsubscribe',
        'opt out',
        'opt-out',
    ];

    /**
     * Short, ambiguous command tokens — matched ONLY when they are the entire
     * message (after trimming surrounding punctuation), never as a substring.
     *
     * @var list<string>
     */
    private const COMMANDS = [
        'stop',
        'إيقاف',
        'ايقاف',
    ];

    /**
     * Whether the text expresses a desire to unsubscribe from bulk messaging.
     */
    public function wantsOptOut(string $text): bool
    {
        $haystack = mb_strtolower(trim($text));

        if ($haystack === '') {
            return false;
        }

        // Explicit phrases: containment is safe (they are unambiguous).
        foreach (self::PHRASES as $phrase) {
            if (str_contains($haystack, $phrase)) {
                return true;
            }
        }

        // Short commands: only when the WHOLE message is that command, so
        // "bus stop" / "don't stop" / "إيقاف الخدمة جيدة" are NOT opt-outs.
        // Strip surrounding whitespace + punctuation with a Unicode-aware regex
        // (the /u flag) — a byte-wise trim() mask would corrupt multibyte Arabic.
        $command = preg_replace('/^[\s\p{P}\p{S}]+|[\s\p{P}\p{S}]+$/u', '', $haystack) ?? $haystack;

        return in_array($command, self::COMMANDS, true);
    }
}
