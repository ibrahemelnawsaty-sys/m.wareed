# وريد — منصة بوتات واتساب الذكية (Wareed WhatsApp AI Bot SaaS)

منصة **SaaS متعددة المستأجرين** على Laravel 12، تربط كل عميل برقم واتساب خاص وقاعدة معرفة خاصة،
وترد آلياً عبر **Gemini 2.5 Flash-Lite** — مصمّمة لتعمل بانضباط صارم على **استضافة Hostinger المشتركة**.

- **الإنتاج:** https://m.wareed.vip/
- **الحزمة:** Laravel 12 · PHP 8.2+ · MySQL 8 (إنتاج) / SQLite (تطوير) · Sanctum · Blade + Alpine.js + Tailwind
- **التكامل:** WhatsApp Cloud API الرسمي (Webhook) · Gemini عبر Laravel HTTP

> ⚖️ هذا المشروع محكوم بـ **دستور هندسي مُلزِم**. اقرأ [`CLAUDE.md`](CLAUDE.md) و[`docs/wareed-engineering-constitution.html`](docs/wareed-engineering-constitution.html) قبل أي مساهمة. القواعد قابلة للإنفاذ الآلي، وأي PR يكسر قاعدة يُرفض.

---

## 📐 المعمارية في سطور (الثوابت الحاكمة)

| ADR | القرار | السبب |
|-----|--------|-------|
| **01** | WhatsApp **Cloud API** رسمي فقط | لا عملية دائمة → متوافق مع الاستضافة المشتركة |
| **02** | قاعدة واحدة + `tenant_id` + **Global Scope** مفروض | أخف عزل، يستحيل نسيانه |
| **03** | رد **متزامن** داخل الـ webhook — بلا Queue Workers دائمة | Flash-Lite سريع؛ المهام الثقيلة لـ Cron |
| **04** | حقن المعرفة في الـ prompt أولاً، RAG لاحقاً | ابدأ بسيطاً، أضِف التعقيد عند الحاجة |

التفاصيل الكاملة في [`docs/whatsapp-bot-saas-plan.html`](docs/whatsapp-bot-saas-plan.html).

---

## 🤖 فريق الوكلاء (`.claude/agents/`)

يُحمّل تلقائياً في جلسات Claude Code. ابدأ أي عمل كبير بـ **`wareed-tech-lead`** الذي يفوّض إلى المتخصصين:

| الوكيل | المسؤولية |
|--------|-----------|
| `wareed-tech-lead` | القيادة، التخطيط، فرض الدستور، التنسيق، المراجعة النهائية |
| `wareed-backend-engineer` | تعدد المستأجرين، النماذج، الهجرات، الخدمات، Sanctum |
| `wareed-whatsapp-engineer` | webhook، التوقيع، idempotency، الإرسال، نافذة 24س |
| `wareed-ai-engineer` | Gemini، بناء الـ prompt، حقن المعرفة، ميزانية التوكنز |
| `wareed-frontend-engineer` | لوحة Blade + Alpine + Tailwind (RTL، فخامة) |
| `wareed-qa-engineer` | Pest، اختبارات العزل والـ webhook، CI |
| `wareed-security-reviewer` | مراجعة عدائية قبل دمج أي مسار حسّاس |

---

## 🚀 التشغيل المحلي (Laravel Herd)

```bash
composer install
cp .env.example .env          # ثم املأ الأسرار محلياً (لا ترفعها)
php artisan key:generate
php artisan migrate            # SQLite افتراضياً
npm install && npm run dev     # أصول الواجهة
php artisan serve              # أو عبر Herd: http://m-wareed.test
```

---

## ✅ الحُرّاس الآلية (الدستور §2) — شغّلها قبل أي دمج

```bash
vendor/bin/pint                       # التنسيق
vendor/bin/phpstan analyse            # التحليل الساكن (Larastan, level 5)
php artisan test                      # الاختبارات (عزل المستأجرين + webhook + ...)
```

CI في [`.github/workflows/ci.yml`](.github/workflows/ci.yml) يفرض الثلاثة. **ممنوع `--no-verify`.**

---

## 🌐 النشر على Hostinger (إنتاج)

1. في `.env` على الخادم فقط (لا في المستودع):
   ```
   APP_ENV=production · APP_DEBUG=false · APP_URL=https://m.wareed.vip
   DB_CONNECTION=mysql · DB_DATABASE=u828479444_M · DB_USERNAME=u828479444_M · DB_PASSWORD=********
   QUEUE_CONNECTION=database
   WHATSAPP_APP_SECRET / WHATSAPP_VERIFY_TOKEN / GEMINI_API_KEY = ********
   ```
2. أوامر ما بعد النشر:
   ```bash
   composer install --optimize-autoloader --no-dev
   php artisan migrate --force
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
3. Cron كل دقيقة (بلا عامل دائم — ADR-03/§14):
   ```
   * * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1
   * * * * * cd /path && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
   ```
4. فعّل SSL على `m.wareed.vip`، واضبط webhook واتساب على `https://m.wareed.vip/api/whatsapp/webhook`.

---

## 🔒 الأمان (الدستور §13)

- **لا أسرار في الكود أو الوثائق.** كل المفاتيح وكلمة مرور القاعدة في `.env` فقط (مُتجاهَل في git).
- الأسرار في القاعدة مشفّرة (`encrypted` cast): توكنات واتساب، مفاتيح Gemini.
- عزل المستأجرين عبر Global Scope + اختبارات IDOR. تحقّق من التوقيع + idempotency على كل webhook.

---

## 📁 بنية المستودع

```
M.Wareed/
├── CLAUDE.md                  # تعليمات وكلاء AI (تُحمّل كل جلسة)
├── README.md
├── .claude/agents/            # فريق الوكلاء (7)
├── .github/                   # CI + قالب PR (فحص دائرة التأثير)
├── docs/                      # الخطة المعمارية + الدستور (HTML)
├── app/ · database/ · routes/ · config/ · resources/ · tests/   # Laravel
└── phpstan.neon · pint.json
```

---

*دستور وريد الهندسي v1.0 — إدارة الهندسة / CTO · هذه القواعد مُلزِمة للبشر ووكلاء الذكاء الاصطناعي معاً.*
