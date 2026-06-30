<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSiteRequest;
use App\Services\Settings\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Super-admin management of the PUBLIC site content — the marketing landing-page
 * copy and its SEO metadata (Phase 4h).
 *
 * Unlike {@see SettingsController} (which guards platform secrets), the values
 * here are public copy, so the page DOES render the current stored value into
 * each field for editing. They remain untrusted input: every field is escaped
 * with `{{ }}` on the landing page (§13). A blank field on update() writes null,
 * which makes the landing page fall back to its hard-coded default — a deliberate
 * "reset to default", not a destructive wipe of unrelated rows (§3).
 */
class SiteController extends Controller
{
    /**
     * The editable site-content keys. Single source of truth shared by edit()
     * (to read current values) and update() (to know which keys to write).
     *
     * @var list<string>
     */
    private const FIELDS = [
        'brand_name',
        'contact_email',
        'contact_phone',
        'contact_address',
        'hero_eyebrow',
        'hero_title',
        'hero_subtitle',
        'hero_cta',
        'announcement',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    /**
     * Default rows the editor is seeded with before anything is saved, mirroring
     * the landing page's hard-coded copy so the admin edits the live text rather
     * than an empty form. The landing page keeps its own copy of these defaults
     * (it must render standalone); these are only the editor's starting point.
     *
     * @var list<array{title: string, description: string}>
     */
    private const FEATURES_DEFAULT = [
        ['title' => 'رد آلي ذكي', 'description' => 'يرد البوت على استفسارات عملائك فوراً بأسلوب عملك، داخل نافذة واتساب الرسمية — دون انتظار.'],
        ['title' => 'قاعدة معرفة خاصة', 'description' => 'أضف منتجاتك وسياساتك وأسئلتك الشائعة، فيرد البوت بمعلومات عملك أنت تحديداً، لا إجابات عامة.'],
        ['title' => 'تعدد نماذج الذكاء', 'description' => 'اختر النموذج الأنسب: Gemini أو ChatGPT أو DeepSeek — وبدّل بينها في أي وقت دون تغيير شيء.'],
        ['title' => 'لوحة تحكم وتحليلات', 'description' => 'تابع المحادثات والاستهلاك والتكلفة لحظياً من لوحة عربية أنيقة وسهلة على الجوال والحاسب.'],
        ['title' => 'مختبر تجربة فوري', 'description' => 'جرّب بوتك حيّاً قبل ربط الرقم — اكتب رسالة وشاهد الرد لحظياً للتأكد من جودته.'],
        ['title' => 'أمان وعزل تام', 'description' => 'بيانات كل عميل معزولة ومشفّرة بالكامل، عبر واتساب Cloud API الرسمي من Meta — بأعلى معايير الأمان.'],
    ];

    /**
     * @var list<array{question: string, answer: string}>
     */
    private const FAQ_DEFAULT = [
        ['question' => 'هل أحتاج خبرة تقنية؟', 'answer' => 'لا إطلاقاً. كل شيء يُدار من لوحة تحكم عربية بسيطة — تسجّل، تربط رقمك، وتضيف معلوماتك بخطوات واضحة.'],
        ['question' => 'هل يستخدم رقم واتساب الخاص بي؟', 'answer' => 'نعم. يرد البوت من رقم عملك أنت عبر واتساب Cloud API الرسمي من Meta — لا أرقام مشتركة.'],
        ['question' => 'هل بياناتي آمنة؟', 'answer' => 'بالكامل. بيانات كل عميل معزولة ومشفّرة، والمفاتيح محمية، ولا يصل أحد لبيانات عمل آخر.'],
        ['question' => 'كم تكلفة الردود؟', 'answer' => 'ردود واتساب داخل نافذة الـ24 ساعة مجانية من Meta، وتكلفة الذكاء الاصطناعي محسوبة وشفافة في لوحتك.'],
        ['question' => 'متى يبدأ بوتي بالعمل؟', 'answer' => 'فور موافقة الإدارة على حسابك وربط رقمك وإضافة معرفتك — عادةً خلال دقائق.'],
    ];

    public function __construct(private readonly SiteSettings $site) {}

    public function edit(): View
    {
        // Public copy: pass the current stored value (or null) for each field so
        // the admin edits what is live. Nothing here is a secret.
        $values = [];
        foreach (self::FIELDS as $field) {
            $values[$field] = $this->site->get($field);
        }

        // The two repeater sections are seeded with the live JSON lists, or the
        // hard-coded defaults when unset/blank/corrupt (getList never throws), so
        // the editor always opens on the copy a visitor currently sees.
        return view('admin.site.edit', [
            'values' => $values,
            'features' => $this->site->getList('features', self::FEATURES_DEFAULT),
            'faq' => $this->site->getList('faq', self::FAQ_DEFAULT),
        ]);
    }

    public function update(UpdateSiteRequest $request): RedirectResponse
    {
        // Write every editable field. A blank/omitted field is stored as null,
        // which reverts that field to its landing-page default (§3). Only the
        // listed keys are touched — never "delete all then insert".
        foreach (self::FIELDS as $field) {
            $value = $request->validated($field);

            $this->site->set($field, is_string($value) && $value !== '' ? $value : null);
        }

        // Repeater lists: store as JSON, or null when empty so the landing page
        // reverts to its default (§3). The rows were already cleaned (empty rows
        // dropped, values trimmed to strings) in the FormRequest, so json_encode
        // here only ever sees a list of {title,description}/{question,answer}.
        $this->setList('features', $request->validated('features'));
        $this->setList('faq', $request->validated('faq'));

        return redirect()
            ->route('admin.site.edit')
            ->with('status', 'site-updated');
    }

    /**
     * Persist a validated repeater list as JSON, or null when it is empty.
     *
     * @param  mixed  $rows
     */
    private function setList(string $key, $rows): void
    {
        $rows = is_array($rows) ? array_values($rows) : [];

        if ($rows === []) {
            $this->site->set($key, null);

            return;
        }

        $json = json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->site->set($key, $json !== false ? $json : null);
    }
}
