<?php

declare(strict_types=1);

use App\Services\Inbox\MenuTriggerDetector;

/*
| Phase 7b: firing the menu wrongly interrupts a real question with a list AND
| skips the AI for that turn, so detection is tuned AGAINST false positives.
| Short standalone tokens ('menu', 'قائمة') match only as the WHOLE message;
| definite phrases ('القائمة', 'الخدمات', 'services') match anywhere.
*/

test('menu detection matches commands only as the whole message and phrases anywhere', function (string $text, bool $expected) {
    expect((new MenuTriggerDetector)->wantsMenu($text))->toBe($expected);
})->with([
    // Short commands — whole message only.
    'bare menu (en)' => ['menu', true],
    'uppercase MENU' => ['MENU', true],
    'menu with punctuation' => ['menu!', true],
    'bare qaema (ar)' => ['قائمة', true],
    'qaema with period' => ['قائمة.', true],

    // False positives that must NOT trigger the menu (context around the word).
    'food menu request' => ['أريد قائمة طعام', false],
    'menu prices question' => ['menu prices?', false],
    'qaema in a sentence' => ['ما هي قائمة الأسعار لديكم', false],

    // Definite phrases — containment anywhere is fine.
    'al-qaema bare' => ['القائمة', true],
    'al-qaema in sentence' => ['أرني القائمة من فضلك', true],
    'al-khadamat' => ['الخدمات', true],
    'al-khadamat in sentence' => ['ما هي الخدمات المتوفرة؟', true],
    'services en' => ['show me your services', true],

    // Unrelated / empty.
    'greeting' => ['مرحبا كيف حالك', false],
    'plain question' => ['كم سعر التوصيل؟', false],
    'empty' => ['', false],
    'whitespace' => ['   ', false],
]);
