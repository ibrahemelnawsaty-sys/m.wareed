# CLAUDE.md — تعليمات وكلاء الذكاء الاصطناعي · منصة وريد لبوتات واتساب

> هذا الملف يُحمَّل في كل جلسة. أنت — أيّ وكيل ذكاء اصطناعي يعمل على هذا المستودع — **مُلزَم بالدستور الهندسي كالبشر تماماً**. اقرأ هذا الملف أولاً، وارجع إليه قبل أي قرار. عند أي تعارض، **الدستور هو الفيصل**، وأي قاعدة تحتاج تعديلاً تحتاج موافقة CTO.

---

## 0) ما هذا المشروع (السياق في 30 ثانية)

منصة **SaaS متعددة المستأجرين** على **Laravel** تبيعها وريد لعملائها (B2B). كل عميل = مستأجر مستقل، يربط **رقم واتساب خاص به**، يضيف قاعدة معرفته، ويحصل على **بوت ذكي يرد آلياً** على عملائه عبر **Gemini 2.5 Flash-Lite**. التكامل عبر **WhatsApp Cloud API** الرسمي (Webhook + HTTP).

- **الدومين:** https://m.wareed.vip/
- **الاستضافة:** Hostinger **مشتركة** — هذا القيد الحاكم لكل قرار.
- **القاعدة:** MySQL 8 واحدة باسم `u828479444_M` (عزل عبر `tenant_id`).

**المبدأ الأعلى:** أقصى أداء بأقل حِمل على استضافة مشتركة. الخفّة شرط، لا تحسين لاحق.

---

## 1) الوثائق المرجعية الملزِمة

| الوثيقة | الغرض | متى ترجع إليها |
|---|---|---|
| `docs/wareed-engineering-constitution.html` | **الدستور** — 14 مادة + معايير الكود + الإنفاذ | قبل أي مسار حسّاس · المرجع النهائي |
| `docs/whatsapp-bot-saas-plan.html` | الخطة المعمارية + ADRs + المخطط + الحاسبة | عند تصميم أي ميزة |
| `CLAUDE.md` (هذا الملف) | الخلاصة التشغيلية | كل جلسة |

### فريق الوكلاء (`.claude/agents/`)
استخدم `wareed-tech-lead` لقيادة أي عمل كبير؛ يفوّض إلى: `wareed-backend-engineer` · `wareed-whatsapp-engineer` · `wareed-ai-engineer` · `wareed-frontend-engineer` · `wareed-qa-engineer` · `wareed-security-reviewer` (مراجعة عدائية قبل دمج أي مسار حسّاس). كل وكيل مُلزَم بهذا الملف والدستور.

عند الشك، افتح الدستور واقرأ المادة المعنية — لا تعتمد على هذه الخلاصة وحدها للمسارات الحسّاسة.

---

## 2) القواعد غير القابلة للتفاوض (لا تكسرها أبداً)

- ❌ **لا مكتبات واتساب غير رسمية** (Baileys / whatsapp-web.js) — تتطلب عملية دائمة وتُحظر الأرقام. **Cloud API فقط** (ADR-01).
- ❌ **لا عمليات خلفية دائمة** — لا `queue:work` دائم، لا Redis/Horizon/Supervisor/Reverb/WebSockets/Octane (ADR-03, §14).
- ❌ **لا استعلام على جدول مستأجر بلا فلتر المستأجر** — العزل عبر **Global Scope مفروض**، لا `where('tenant_id', ...)` يدوي (ADR-02, §3).
- ❌ **لا سرٍّ بنص صريح** — لا في الكود، لا في الوثائق، لا في الـ Logs. كل سرٍّ في `.env` ومشفّر في القاعدة (§13).
- ❌ **لا معالجة webhook بلا تحقق توقيع + idempotency** (§11).
- ❌ **لا «احذف الكل ثم أدرج»** — المصفوفة الفارغة من الواجهة تعني «لا تغيير»، لا «امحُ» (§3).
- ❌ **لا ابتلاع صامت للأخطاء** — `report($e)` / `throw` / خطأ صريح. لا `Log::error()` ثم متابعة (§3).
- ❌ **لا `float` للمال/التكلفة** — أعداد صحيحة (أصغر وحدة) أو `decimal` (§3).
- ❌ **لا `--no-verify`** ولا تجاوز أي فحص CI (§2).
- ❌ **لا `dd/dump/var_dump/ray/console.log/@`** ولا كود معلّق في الإنتاج (Standards §8).

---

## 3) الثوابت المعمارية (ADRs — لا تُكسر إلا بـ ADR جديد مُعتمَد)

1. **ADR-01 — واتساب:** Cloud API رسمي عبر Webhook. المستأجر يُحدَّد من `phone_number_id`. الرد باسم رقم العميل نفسه.
2. **ADR-02 — التعدد:** قاعدة واحدة + `tenant_id` في كل جدول + `TenantScope` على كل Model.
3. **ADR-03 — المعالجة:** الرد **متزامن داخل الـ webhook** (Flash-Lite سريع 1–3 ث). المهام الثقيلة فقط ← طابور `database` يُشغّله Cron كل دقيقة.
4. **ADR-04 — المعرفة:** حقن المعرفة في الـ system prompt أولاً. RAG فقط عند نمو المعرفة فعلياً — لا تعقيد قبل الحاجة.

---

## 4) الحزمة التقنية

- **Backend:** Laravel 12 · PHP 8.3+ · OPcache
- **DB:** MySQL 8 (`u828479444_M`) — فهرسة `tenant_id` و`phone_number_id`
- **Auth/API:** Laravel Sanctum (API-first)
- **اللوحة:** Blade + Alpine.js + Tailwind (بلا SPA ثقيل)
- **الطابور:** database driver + Cron (للمهام غير العاجلة فقط)
- **الذكاء:** Gemini `gemini-2.5-flash-lite` عبر Laravel HTTP

---

## 5) المسارات الحسّاسة (§1) — القواعد المشدّدة تنطبق هنا

```
Tenancy        → tenants, users, tenant_id, BelongsToTenant, TenantScope
WhatsApp       → whatsapp_accounts, phone_number_id, waba_id, [access_token], [ai_api_key], system_prompt
Conversations  → conversations, messages, [wa_message_id], window_expires_at, direction
Knowledge      → knowledge_documents, knowledge_chunks, embedding
Billing/Usage  → usage_counters, subscriptions, subscription_plans, tokens_in, tokens_out, cost
Identity       → users, roles, permissions, password_resets, Sanctum tokens
```
`[ ]` = عمود **مشفّر** (`encrypted` cast). أي تغيير يمسّ هذه الجداول = **اختبار إلزامي + مراجعة بشرية ثانية**.

---

## 6) قواعد الكود الإلزامية (افعل / لا تفعل)

**عزل المستأجرين** — Global Scope لا تذكّر يدوي:
```php
// ✅
static::addGlobalScope(new TenantScope()); // في booted() على كل Model مرتبط بمستأجر
```

**تحقق توقيع الـ webhook قبل أي معالجة:**
```php
$expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), config('services.whatsapp.app_secret'));
if (! hash_equals($expected, $request->header('X-Hub-Signature-256', ''))) {
    abort(403); // لا تحديد مستأجر، لا استدعاء AI
}
```

**Idempotency قبل أي عمل مكلِّف:**
```php
if (Message::where('wa_message_id', $waMessageId)->exists()) {
    return response('', 200); // مكرر — تجاهل قبل Gemini والإرسال
}
// المخطط: $table->string('wa_message_id')->unique();
```

**تشفير الأسرار:**
```php
protected function casts(): array {
    return ['access_token' => 'encrypted', 'ai_api_key' => 'encrypted'];
}
```

**رصد الأخطاء على المسار الحرج:**
```php
try { $reply = $this->gemini->generate($prompt); }
catch (\Throwable $e) {
    report($e);                          // يصل Sentry/Flare
    $reply = $this->fallbackReply($bot); // رد لائق بدل الصمت/الانهيار
}
```

**مزامنة غير مدمّرة:** إن لم تُرسل الواجهة الحقل ← `return` (لا تغيير). وإلا `upsert`/Diff موجّه — لا `delete()` ثم `createMany()`.

**المال/الاستهلاك:** أعداد صحيحة + Service مالي نقي مُختبَر بـ Pest. العرض عبر مكوّن موحّد null-safe (`<x-money/>`).

---

## 7) سير العمل (Workflow & CI)

- **اختبار قبل الدمج** على أي مسار حسّاس: Pest/PHPUnit على قاعدة حقيقية (§2). bug إنتاجي ← اختبار يفشل أولاً ثم الإصلاح.
- **CI إلزامي:** `Pint` + `Larastan/PHPStan` + كل الاختبارات. أحمر = لا دمج ولا مراجعة.
- **الهجرات (§8):** كل تغيير مخطط عبر Migration فقط — **ممنوع SQL يدوي على الإنتاج**. الآمن يؤتمت، الخطِر (drop/truncate/تغيير نوع/not null) يوقَف لمراجعة. كل Migration لها `down()`.
- **الدمج (§Merge):** انتظر CI أخضر على آخر SHA · لا `--auto` بعد force-push · مراجعة ثانية على المسارات الحسّاسة · افحص بقايا conflict markers بعد rebase.
- **اكتمال الميزة (§10):** أي backend يتطلّب إجراء مستخدم (إعادة ربط رقم، إعادة محاولة إرسال، تأكيد اشتراك) **يجب أن يشمل واجهته** في نفس المسار. لا تترك المستخدم «أعمى».
- **المخرج النهائي (§5):** لا تكتمل مهمة تمسّ الرد بمجرد الحفظ في الجداول — **أرسل رسالة واتساب حيّة** وتأكد من وصول الرد الصحيح وظهور المحادثة في اللوحة.

---

## 8) قبل أي ميزة جوهرية أو مسار حسّاس — البصيرة المعمارية (§9)

وثّق القواعد الخمس قبل الكود (لمحة خفيفة تكفي للبسيط):
1. **محامي الشيطان:** أسوأ كارثة ممكنة (تسريب محادثات بين مستأجرين، رد لرقم خاطئ، حلقة webhook، استنزاف توكنات، حظر رقم العميل).
2. **الأثر البشري:** كيف يتأثر صاحب البوت (العميل المشترِك) / العميل النهائي الذي يحادث البوت / الدعم.
3. **محاكاة النهايات:** رحلة «الرسالة الواحدة» من webhook حتى الرد، و«التوكن الواحد» حتى الفوترة، تحت أسوأ تزامن.
4. **الاحتكام للثوابت:** مطابقة ADRs (عزل، رد متزامن، حدود التكلفة).
5. **الانعكاسية:** مسار تراجع/مفتاح إيقاف موثّق مسبقاً (Feature Flag، git revert، هجرة عكسية).

**+ فحص دائرة التأثير (§4):** قبل تعديل أي Service/Route/Job/Migration مشترك — ابحث في كامل المشروع عن كل المستدعين ووثّق الأثر في وصف الـ PR.

---

## 9) واتساب والذكاء الاصطناعي (§11, §12)

- **نافذة 24 ساعة:** الرد الحرّ مسموح فقط داخل نافذة الخدمة (`window_expires_at` مصدر الحقيقة). خارجها يلزم Template مُعتمَد مدفوع.
- **الـ webhook يردّ `200` بسرعة**؛ أي عمل قد يتجاوز المهلة ← طابور database.
- **Throttle لكل `phone_number_id`** لاحترام حدود Meta وتفادي Spam.
- **سياسة Meta:** المنصة Tech Provider؛ راجِع شروط مزوّد خدمة AI قبل أي إطلاق/ربط تجاري جديد.
- **Gemini:** المدخلات (رسائل العملاء + المعرفة) **غير موثوقة** — حصّن الـ system prompt (مقاومة Prompt Injection). أرسل آخر N جولة فقط. سقف استهلاك لكل مستأجر عبر `usage_counters`. لا تسجّل المفاتيح في أي Log. عند فشل النموذج: خطأ صريح + رد احتياطي، لا حلقة إعادة محاولة تستنزف التوكنز.

---

## 10) الأمان والأسرار والبيئة (§13)

- **كلمة مرور القاعدة لا تُكتب هنا ولا في أي ملف بالمستودع.** مكانها `.env` على الخادم فقط. تأكد أن `.env` ضمن `.gitignore`. (إن ظهرت كلمة مرور في أي محادثة/سجل — انصح بتدويرها.)
- متغيرات البيئة الإنتاجية (القيم الحقيقية في `.env` فقط):

```
APP_ENV=production · APP_URL=https://m.wareed.vip · APP_DEBUG=false
DB_CONNECTION=mysql · DB_HOST=localhost · DB_DATABASE=u828479444_M · DB_USERNAME=u828479444_M · DB_PASSWORD=********
QUEUE_CONNECTION=database
WHATSAPP_APP_SECRET=******** · WHATSAPP_VERIFY_TOKEN=********
GEMINI_API_KEY=******** · GEMINI_MODEL=gemini-2.5-flash-lite
```

- **أمان:** Validation على كل فورم · `$fillable` صريح (لا Mass Assignment) · CSRF على اللوحة · Rate Limiting (دخول + API لكل مستأجر) · HTTPS مفروض · Policies/Gates + اختبار IDOR · مبدأ أقل صلاحية.

---

## 11) أوامر الإنتاج بعد النشر

```bash
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
composer install --optimize-autoloader --no-dev
# Cron كل دقيقة (لا عامل دائم):
* * * * * cd /path && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
```

---

## 12) خلاصة سلوكك كوكيل

1. اقرأ الدستور قبل المسارات الحسّاسة — لا تخمّن.
2. لكل تغيير: اختبار يحرسه + فحص دائرة تأثير إن كان مشتركاً.
3. عزل المستأجرين والأسرار المشفّرة ليسا اختياريين — تحقق منهما في كل مرة.
4. لا تترك خطأً صامتاً ولا ميزة بلا واجهتها.
5. تحقق من المخرج النهائي كما يراه العميل، لا الجداول وحدها.
6. عند الشك في قاعدة: ارجع للدستور، أو اسأل — لا تكسرها.
