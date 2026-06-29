<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>وريد — بوتات واتساب الذكية لأعمالك</title>
    <meta name="description" content="منصة وريد: اربط رقم واتساب عملك واترك بوتاً ذكياً يرد على عملائك آلياً عبر الذكاء الاصطناعي.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper font-sans text-ink antialiased">

    <div class="relative min-h-screen overflow-hidden bg-night text-white">
        <!-- خلفية -->
        <div class="pointer-events-none absolute inset-0 opacity-60"
             style="background:
                radial-gradient(900px 500px at 80% -10%, rgba(22,200,146,.18), transparent 60%),
                radial-gradient(700px 400px at 10% 110%, rgba(184,134,46,.12), transparent 60%);"></div>

        <!-- الشريط العلوي -->
        <header class="relative z-10 mx-auto flex max-w-6xl items-center justify-between px-6 py-6">
            <div class="flex items-center gap-2 text-lg font-bold">
                <span class="grid h-9 w-9 place-items-center rounded-xl bg-signal/15 text-signal">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5l1.2-3.6A8.25 8.25 0 1 1 7.5 21Z"/></svg>
                </span>
                وريد
            </div>
            <nav class="flex items-center gap-3 text-sm">
                @auth
                    <a href="{{ route('dashboard') }}" class="rounded-xl bg-emerald px-5 py-2 font-semibold text-white transition hover:bg-emerald-deep">لوحة التحكم</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-xl px-4 py-2 font-medium text-white/80 transition hover:text-white">تسجيل الدخول</a>
                    <a href="{{ route('register') }}" class="rounded-xl bg-emerald px-5 py-2 font-semibold text-white transition hover:bg-emerald-deep">إنشاء حساب</a>
                @endauth
            </nav>
        </header>

        <!-- البطل -->
        <main class="relative z-10 mx-auto max-w-6xl px-6">
            <section class="py-16 text-center sm:py-24">
                <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-xs font-medium text-signal">
                    <span class="h-2 w-2 rounded-full bg-signal"></span>
                    منصة SaaS · واتساب + ذكاء اصطناعي
                </span>
                <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-bold leading-tight tracking-tight sm:text-5xl">
                    بوت واتساب ذكي يرد على عملائك<br>
                    <span class="text-signal">تلقائياً، على مدار الساعة</span>
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-white/70 sm:text-lg">
                    اربط رقم واتساب عملك، أضف معرفتك، واترك الذكاء الاصطناعي (Gemini) يرد على استفسارات عملائك
                    بأسلوبك — مع لوحة تحكم كاملة وتحليلات ومختبر تجربة فوري.
                </p>
                <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    @auth
                        <a href="{{ route('dashboard') }}" class="w-full rounded-xl bg-emerald px-7 py-3 text-center font-semibold text-white shadow-luxe transition hover:bg-emerald-deep sm:w-auto">اذهب إلى لوحة التحكم</a>
                    @else
                        <a href="{{ route('register') }}" class="w-full rounded-xl bg-emerald px-7 py-3 text-center font-semibold text-white shadow-luxe transition hover:bg-emerald-deep sm:w-auto">ابدأ مجاناً — أنشئ حسابك</a>
                        <a href="{{ route('login') }}" class="w-full rounded-xl border border-white/15 px-7 py-3 text-center font-semibold text-white/90 transition hover:bg-white/5 sm:w-auto">لديّ حساب</a>
                    @endauth
                </div>
            </section>

            <!-- المزايا -->
            <section class="grid gap-4 pb-20 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-right">
                    <div class="grid h-11 w-11 place-items-center rounded-xl bg-signal/15 text-signal">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 1 1-3.5-7.1L21 4l-.9 3.4A8.96 8.96 0 0 1 21 12Z"/></svg>
                    </div>
                    <h3 class="mt-4 font-bold">رد آلي ذكي</h3>
                    <p class="mt-1 text-sm text-white/65">يرد البوت بأسلوب عملك عبر Gemini داخل نافذة الـ24 ساعة — مجاناً عبر Cloud API الرسمي.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-right">
                    <div class="grid h-11 w-11 place-items-center rounded-xl bg-gold/15 text-gold">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.04A8.97 8.97 0 0 0 6 3.75c-1.05 0-2.06.18-3 .51v14.25A8.99 8.99 0 0 1 6 18c2.3 0 4.41.87 6 2.29m0-14.25a8.97 8.97 0 0 1 6-2.29c1.05 0 2.06.18 3 .51v14.25A8.99 8.99 0 0 0 18 18a8.97 8.97 0 0 0-6 2.29m0-14.25v14.25"/></svg>
                    </div>
                    <h3 class="mt-4 font-bold">قاعدة معرفة خاصة</h3>
                    <p class="mt-1 text-sm text-white/65">أضف منتجاتك وسياساتك وأسئلتك الشائعة، فيرد البوت بمعلومات عملك أنت تحديداً.</p>
                </div>
                <div class="rounded-2xl border border-white/10 bg-white/5 p-6 text-right">
                    <div class="grid h-11 w-11 place-items-center rounded-xl bg-white/10 text-white">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.5 12 4l9 9.5M5 12v7a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-7"/></svg>
                    </div>
                    <h3 class="mt-4 font-bold">لوحة تحكم وتحليلات</h3>
                    <p class="mt-1 text-sm text-white/65">تابع المحادثات والاستهلاك والتكلفة، وجرّب بوتك حيّاً من مختبر البوت قبل ربط الرقم.</p>
                </div>
            </section>
        </main>

        <footer class="relative z-10 border-t border-white/10">
            <div class="mx-auto flex max-w-6xl flex-col items-center justify-between gap-2 px-6 py-6 text-sm text-white/50 sm:flex-row">
                <span>© {{ date('Y') }} وريد — جميع الحقوق محفوظة.</span>
                <span class="font-mono text-xs">m.wareed.vip</span>
            </div>
        </footer>
    </div>

</body>
</html>
