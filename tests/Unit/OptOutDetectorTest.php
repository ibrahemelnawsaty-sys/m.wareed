<?php

declare(strict_types=1);

use App\Services\Inbox\OptOutDetector;

/*
| Phase 6d review fix: opt-out is permanent and silently drops a legitimate
| contact, so detection is tuned AGAINST false positives. Short ambiguous
| tokens ('stop', 'إيقاف') match only as the WHOLE message; explicit phrases
| ('unsubscribe', 'إلغاء الاشتراك') match anywhere.
*/

test('opt-out detection matches commands only as the whole message and phrases anywhere', function (string $text, bool $expected) {
    expect((new OptOutDetector)->wantsOptOut($text))->toBe($expected);
})->with([
    // Short commands — whole message only.
    'bare stop' => ['stop', true],
    'uppercase STOP' => ['STOP', true],
    'stop with period' => ['stop.', true],
    'arabic iqaf' => ['إيقاف', true],
    'arabic ayqaf' => ['ايقاف', true],

    // False positives that must NOT opt anyone out.
    'bus stop' => ['bus stop', false],
    'dont stop' => ["please don't stop", false],
    'stop in a sentence' => ['the bus stop is near my house', false],
    'iqaf in a sentence' => ['إيقاف الخدمة فكرة سيئة', false],

    // Explicit phrases — containment anywhere is fine.
    'unsubscribe bare' => ['unsubscribe', true],
    'unsubscribe in sentence' => ['please unsubscribe me now', true],
    'opt out' => ['opt out', true],
    'arabic phrase bare' => ['الغاء الاشتراك', true],
    'arabic phrase in sentence' => ['أريد إلغاء الاشتراك من فضلك', true],

    // Unrelated / empty.
    'greeting' => ['مرحبا كيف حالك', false],
    'empty' => ['', false],
]);
