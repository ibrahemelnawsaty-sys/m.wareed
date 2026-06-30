<?php

declare(strict_types=1);

use App\Services\Inbox\HandoffDetector;

beforeEach(function () {
    $this->detector = new HandoffDetector;
});

test('it detects an explicit request for a human (Arabic + English)', function (string $text) {
    expect($this->detector->wantsHuman($text))->toBeTrue();
})->with([
    'أريد التحدث مع موظف',
    'ممكن خدمة العملاء؟',
    'عايز أكلم حد بشري',
    'حابب أحكي مع إنسان',
    'وين المندوب؟',
    'أريد تحويل المكالمة لموظف',
    'عندي شكوى',
    'I want to talk to an agent',
    'connect me to a human please',
    'I need a representative',
    'can I get support',
    'I have a complaint',
    'let me talk to someone',
    // Case-insensitive on the English side.
    'AGENT please',
]);

test('it returns false for ordinary product questions', function (string $text) {
    expect($this->detector->wantsHuman($text))->toBeFalse();
})->with([
    'هل لديكم توصيل؟',
    'كم سعر المنتج؟',
    'متى يفتح المتجر؟',
    'do you ship to Riyadh?',
    'what are your prices?',
    '',
    '   ',
]);
