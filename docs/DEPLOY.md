# دليل النشر — وريد على Hostinger (m.wareed.vip)

نشر عبر **Git (hPanel) + SSH**. كل الأسرار على الخادم فقط (الدستور §13).
الأصول المُجمَّعة (`public/build`) مُضمَّنة في المستودع لأن الاستضافة المشتركة بلا Node.

> **القيم الثابتة:** النطاق `m.wareed.vip` · قاعدة البيانات والمستخدم `u828479444_M` · المضيف `localhost`.

---

## الخطوة 0 — رفع المشروع إلى GitHub (مرة واحدة)

أنشئ مستودعاً **خاصاً (Private)** على GitHub باسم `wareed` (لا تجعله عاماً — مشروع تجاري). ثم من جهازك في جذر المشروع:

```bash
git remote add origin https://github.com/<حسابك>/wareed.git
git push -u origin main
```

> `.env` و`vendor/` و`node_modules/` مُتجاهلة تلقائياً — لن تُرفع أسرار. تأكّد أن المستودع **Private**.

---

## الخطوة 1 — إعداد hPanel (مرة واحدة)

1. **النطاق الفرعي:** Hostinger ← Domains ← Subdomains ← أنشئ `m` تحت `wareed.vip` (إن لم يكن موجوداً).
2. **قاعدة البيانات:** تأكّد أن `u828479444_M` موجودة وأن المستخدم `u828479444_M` يملك **All Privileges** عليها (Databases ← Management).
3. **SSL:** Security ← SSL ← فعّل شهادة مجانية (Let's Encrypt) لـ `m.wareed.vip`، وفعّل **Force HTTPS**.

---

## الخطوة 2 — ربط Git في hPanel (مرة واحدة)

hPanel ← **Advanced ← Git** ← Create:
- **Repository:** رابط مستودع GitHub (HTTPS). للمستودع الخاص: أضِف Deploy Key أو استخدم Personal Access Token.
- **Branch:** `main`
- **Directory:** مثلاً `domains/m.wareed.vip` (مجلد النشر — سمّه `repo` لاحقاً في الأوامر).

بعد الإنشاء اضغط **Deploy** ليسحب الكود.

---

## الخطوة 3 — توجيه جذر المستند إلى `/public` (حرج)

Laravel يخدم من مجلد `public` فقط. في hPanel:
- Domains ← `m.wareed.vip` ← **Document Root** ← اضبطه على: `<مجلد النشر>/public`
  (مثال: `domains/m.wareed.vip/public`).

> هذه أهم خطوة على الاستضافة المشتركة. بدونها يظهر كود المصدر أو خطأ 403/500.

---

## الخطوة 4 — الإعداد الأول عبر SSH (مرة واحدة)

ادخل عبر SSH وانتقل لمجلد النشر:

```bash
cd ~/domains/m.wareed.vip          # عدّل حسب مسارك

# 1) ملف البيئة
cp .env.production.example .env
nano .env                          # املأ: DB_PASSWORD, WHATSAPP_APP_SECRET, WHATSAPP_VERIFY_TOKEN, GEMINI_API_KEY

# 2) الحزم + المفتاح + الهجرات
composer install --no-dev --optimize-autoloader
php artisan key:generate           # مفتاح إنتاج جديد (تشفير الأسرار §13)
php artisan migrate --force        # القاعدة فارغة — يبني كل الجداول
php artisan storage:link

# 3) الكاش + الصلاحيات
php artisan optimize               # config + route + view cache
chmod -R 775 storage bootstrap/cache
```

**القيم التي تملؤها في `.env`:**
| المفتاح | المصدر |
|---|---|
| `DB_PASSWORD` | كلمة مرور قاعدة `u828479444_M` |
| `WHATSAPP_VERIFY_TOKEN` | اختر قيمة قوية، وضع **نفسها** في Meta (الخطوة 6) |
| `WHATSAPP_APP_SECRET` | Meta ← Settings ← Basic ← App Secret |
| `GEMINI_API_KEY` | مفتاح Gemini الخاص بك |

---

## الخطوة 5 — Cron (مرة واحدة) — بلا عامل دائم (ADR-03/§14)

hPanel ← Advanced ← **Cron Jobs** ← أضِف وظيفتين، كل **دقيقة** (`* * * * *`)، عدّل المسار:

```bash
cd ~/domains/m.wareed.vip && php artisan schedule:run >> /dev/null 2>&1
cd ~/domains/m.wareed.vip && php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

---

## الخطوة 6 — ربط Webhook واتساب في Meta

Meta App Dashboard ← WhatsApp ← **Configuration** ← Webhook:
- **Callback URL:** `https://m.wareed.vip/api/whatsapp/webhook`
- **Verify token:** نفس قيمة `WHATSAPP_VERIFY_TOKEN` في `.env`
- اضغط **Verify and Save** ← ثم اشترك في حقل **`messages`**.
- ضع **App Secret** في `.env` (`WHATSAPP_APP_SECRET`) — ضروري لتحقق توقيع رسائل POST.
- **انشر التطبيق (Publish App)** — بدون نشر تصلك رسائل تجريبية فقط.

> لكل عميل: يدخل `phone_number_id` و`access_token` ورقمه من صفحة **«ربط واتساب»** في اللوحة.

---

## التحديثات المستقبلية (كل نشر)

```bash
# على جهازك:
git push                    # ادفع التغييرات (وأصول public/build المُجمَّعة)
# في hPanel ← Git ← Deploy (أو فعّل النشر التلقائي)
# ثم عبر SSH:
bash deploy.sh             # تثبيت + هجرات + كاش (سكربت جاهز في الجذر)
```

> إن غيّرت الواجهة محلياً: شغّل `npm run build` ثم `git add public/build && git commit && git push` لتُنشر الأصول الجديدة.

---

## التحقق بعد النشر (Smoke test)

1. افتح `https://m.wareed.vip` ← صفحة الترحيب + قفل HTTPS أخضر.
2. `/register` ← أنشئ حساباً ← تدخل اللوحة.
3. **مختبر البوت** ← أرسل رسالة ← يأتي رد من Gemini (يؤكّد المفتاح + الإعداد).
4. تحقّق Webhook: `curl "https://m.wareed.vip/api/whatsapp/webhook?hub.mode=subscribe&hub.verify_token=<التوكن>&hub.challenge=ok123"` ← يجب أن يُرجع `ok123`.

---

## استكشاف الأخطاء

| العَرَض | الحل |
|---|---|
| 500 / صفحة بيضاء | `php artisan optimize:clear` ثم راجع `storage/logs/laravel.log`. تأكّد `APP_KEY` مُولَّد. |
| يظهر كود المصدر / 403 | جذر المستند لا يشير إلى `/public` (الخطوة 3). |
| خطأ اتصال قاعدة | راجع `DB_*` في `.env`؛ `DB_HOST=localhost`؛ صلاحيات المستخدم. |
| تعديل `.env` بلا أثر | أعِد `php artisan config:cache` بعد أي تعديل. |
| الطابور لا يعمل | تأكّد من Cron (الخطوة 5) والمسار الصحيح. |
| Webhook يفشل التحقق | التطبيق غير منشور، أو `WHATSAPP_VERIFY_TOKEN` لا يطابق Meta، أو SSL غير مفعّل. |

---

## الأمان (الدستور §13)
- لا أسرار في المستودع؛ كلها في `.env` على الخادم. `APP_DEBUG=false` في الإنتاج.
- بعد التأكد من العمل: **دوّر** كلمة مرور القاعدة ومفتاح Gemini وتوكن واتساب (ظهرت في محادثة الإعداد).
