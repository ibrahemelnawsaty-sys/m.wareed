<?php

declare(strict_types=1);

namespace App\Services\Inbox;

/**
 * Detects, from an inbound customer message, whether the customer is asking to
 * speak with a human agent rather than the bot (§11 handoff). Deliberately a
 * pure, side-effect-free function so it is trivially unit-testable and cheap to
 * run on every inbound message before any AI cost is incurred.
 *
 * The match is intentionally simple keyword containment (no NLP) — keeping it
 * lightweight on shared hosting and predictable. False positives only route the
 * customer to a human, which is the safe direction.
 */
class HandoffDetector
{
    /**
     * Trigger phrases (Arabic + English), all lowercase. Containment match.
     *
     * @var list<string>
     */
    private const KEYWORDS = [
        // Arabic
        'موظف',
        'خدمة العملاء',
        'بشري',
        'إنسان',
        'انسان',
        'مندوب',
        'ممثل',
        'أكلم حد',
        'اكلم حد',
        'أكلّم حد',
        'تحويل',
        'شكوى',
        // English
        'agent',
        'human',
        'representative',
        'support',
        'complaint',
        'talk to someone',
    ];

    /**
     * Whether the text expresses a desire to reach a human agent.
     */
    public function wantsHuman(string $text): bool
    {
        $haystack = mb_strtolower($text);

        if (trim($haystack) === '') {
            return false;
        }

        foreach (self::KEYWORDS as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }
}
