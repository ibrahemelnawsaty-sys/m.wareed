<?php

declare(strict_types=1);

namespace App\Services\Inbox;

/**
 * Detects, from an inbound customer message, whether the customer is explicitly
 * asking to see the interactive service menu (Phase 7b, §11). A pure,
 * side-effect-free keyword test — cheap to run on every inbound message and
 * trivially unit-testable, mirroring {@see OptOutDetector}.
 *
 * Tuned AGAINST false positives, because firing the menu wrongly interrupts a
 * real question with a list and (worse) SKIPS the AI for that turn. Short
 * standalone words ('menu', 'قائمة') match only when they are the WHOLE message
 * — the "send the word 'menu' to see options" convention — so an ordinary
 * sentence that merely contains the word ("أريد قائمة طعام", "menu prices?") is
 * never mistaken for a menu request. Longer unambiguous phrases ('الخدمات',
 * 'القائمة', 'services') match anywhere.
 */
class MenuTriggerDetector
{
    /**
     * Unambiguous phrases — matched by containment anywhere. All lowercase. The
     * leading "ال" makes these read as a definite request for OUR list/services
     * rather than the generic noun, so containment stays safe.
     *
     * @var list<string>
     */
    private const PHRASES = [
        'القائمة',
        'الخدمات',
        'قائمة الخدمات',
        'services',
    ];

    /**
     * Short, ambiguous tokens — matched ONLY when they are the entire message
     * (after stripping surrounding punctuation), never as a substring, so
     * "أريد قائمة طعام" / "menu prices?" are NOT menu requests.
     *
     * @var list<string>
     */
    private const COMMANDS = [
        'قائمة',
        'menu',
    ];

    /**
     * Whether the text is asking to see the service menu.
     */
    public function wantsMenu(string $text): bool
    {
        $haystack = mb_strtolower($text);

        if (trim($haystack) === '') {
            return false;
        }

        // Explicit phrases: containment is safe (they are unambiguous).
        foreach (self::PHRASES as $phrase) {
            if (str_contains($haystack, $phrase)) {
                return true;
            }
        }

        // Short commands: only when the WHOLE message is that command. Strip
        // surrounding whitespace + punctuation with a Unicode-aware regex (the
        // /u flag) — a byte-wise trim() mask would corrupt multibyte Arabic.
        $command = preg_replace('/^[\s\p{P}\p{S}]+|[\s\p{P}\p{S}]+$/u', '', $haystack) ?? $haystack;

        return in_array($command, self::COMMANDS, true);
    }
}
