<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>وريد — بوتات واتساب الذكية لأعمالك | رد آلي بالذكاء الاصطناعي</title>
    <meta name="description" content="منصة وريد: اربط رقم واتساب عملك واترك بوتاً ذكياً يرد على عملائك آلياً عبر الذكاء الاصطناعي (Gemini · ChatGPT · DeepSeek) — على مدار الساعة، بأسلوب علامتك التجارية.">
    <meta name="keywords" content="بوت واتساب, واتساب ذكاء اصطناعي, رد آلي واتساب, خدمة عملاء آلية, شات بوت, وريد">
    <meta property="og:title" content="وريد — بوتات واتساب الذكية لأعمالك">
    <meta property="og:description" content="بوت واتساب ذكي يرد على عملائك تلقائياً على مدار الساعة.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://m.wareed.vip/">
    <meta property="og:locale" content="ar_AR">
    <link rel="canonical" href="https://m.wareed.vip/">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-paper font-sans text-ink antialiased">

@php
    $homeRoute = auth()->check()
        ? (auth()->user()->is_admin ? route('admin.dashboard') : route('dashboard'))
        : route('login');
@endphp

<!-- ===== Header ===== -->
<header class="sticky top-0 z-50 border-b border-ink/10 bg-paper/85 backdrop-blur">
    <div class="mx-auto flex max-w-6xl items-center justify-between px-5 py-3.5">
        <a href="#" class="flex items-center gap-2 text-lg font-bold">
            <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald/10 text-emerald">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5l1.2-3.6A8.25 8.25 0 1 1 7.5 21Z"/></svg>
            </span>
            وريد
        </a>
        <nav class="hidden items-center gap-7 text-sm font-medium text-ink-2 md:flex">
            <a href="#features" class="transition hover:text-emerald">المزايا</a>
            <a href="#how" class="transition hover:text-emerald">كيف يعمل</a>
            <a href="#providers" class="transition hover:text-emerald">النماذج</a>
            <a href="#faq" class="transition hover:text-emerald">الأسئلة</a>
        </nav>
        <div class="flex items-center gap-2.5 text-sm">
            @auth
                <a href="{{ $homeRoute }}" class="rounded-xl bg-emerald px-5 py-2 font-semibold text-white transition hover:bg-emerald-deep">لوحة التحكم</a>
            @else
                <a href="{{ route('login') }}" class="hidden rounded-xl px-3 py-2 font-medium text-ink-2 transition hover:text-ink sm:block">دخول</a>
                <a href="{{ route('register') }}" class="rounded-xl bg-emerald px-5 py-2 font-semibold text-white transition hover:bg-emerald-deep">ابدأ مجاناً</a>
            @endauth
        </div>
    </div>
</header>

<!-- ===== Hero ===== -->
<section class="relative overflow-hidden bg-night text-white">
    <div class="pointer-events-none absolute inset-0 opacity-70"
         style="background:
            radial-gradient(1000px 520px at 82% -8%, rgba(22,200,146,.20), transparent 60%),
            radial-gradient(760px 420px at 8% 112%, rgba(184,134,46,.14), transparent 60%);"></div>
    <div class="relative mx-auto max-w-6xl px-5 py-20 text-center sm:py-28">
        <span class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-xs font-medium text-signal">
            <span class="h-2 w-2 animate-pulse rounded-full bg-signal"></span>
            منصة SaaS · واتساب + ذكاء اصطناعي
        </span>
        <h1 class="mx-auto mt-6 max-w-3xl text-4xl font-bold leading-[1.15] tracking-tight sm:text-5xl md:text-6xl">
            بوت واتساب ذكي يرد على عملائك<br>
            <span class="text-signal">تلقائياً، على مدار الساعة</span>
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-white/70 sm:text-lg">
            وريد منصة تربط رقم واتساب عملك ببوت ذكي يفهم عملاءك ويرد عليهم بأسلوب علامتك التجارية —
            مدعوماً بأقوى نماذج الذكاء الاصطناعي (Gemini · ChatGPT · DeepSeek)، مع لوحة تحكم كاملة وتحليلات.
        </p>
        <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
            @auth
                <a href="{{ $homeRoute }}" class="w-full rounded-xl bg-emerald px-7 py-3.5 text-center font-semibold text-white shadow-luxe transition hover:bg-emerald-deep sm:w-auto">اذهب إلى لوحة التحكم</a>
            @else
                <a href="{{ route('register') }}" class="w-full rounded-xl bg-emerald px-7 py-3.5 text-center font-semibold text-white shadow-luxe transition hover:bg-emerald-deep sm:w-auto">ابدأ مجاناً — أنشئ حسابك</a>
                <a href="#how" class="w-full rounded-xl border border-white/15 px-7 py-3.5 text-center font-semibold text-white/90 transition hover:bg-white/5 sm:w-auto">كيف يعمل؟</a>
            @endauth
        </div>

        <!-- Stats -->
        <div class="mx-auto mt-14 grid max-w-3xl grid-cols-2 gap-px overflow-hidden rounded-2xl border border-white/10 bg-white/10 sm:grid-cols-4">
            @foreach ([
                ['24/7', 'رد دون توقف'],
                ['3', 'نماذج ذكاء اصطناعي'],
                ['ثوانٍ', 'زمن الرد'],
                ['100%', 'بأسلوب علامتك'],
            ] as [$v, $k])
                <div class="bg-night px-4 py-5">
                    <div class="text-2xl font-bold text-signal">{{ $v }}</div>
                    <div class="mt-1 text-xs text-white/60">{{ $k }}</div>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- ===== Features ===== -->
<section id="features" class="mx-auto max-w-6xl px-5 py-20">
    <div class="mx-auto max-w-2xl text-center">
        <span class="font-mono text-xs font-semibold uppercase tracking-widest text-emerald">المزايا</span>
        <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">كل ما تحتاجه لأتمتة خدمة عملائك</h2>
    </div>
    <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ([
            ['رد آلي ذكي', 'يرد البوت على استفسارات عملائك فوراً بأسلوب عملك، داخل نافذة واتساب الرسمية — دون انتظار.', 'M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 1 1-3.5-7.1L21 4l-.9 3.4A8.96 8.96 0 0 1 21 12Z', 'emerald'],
            ['قاعدة معرفة خاصة', 'أضف منتجاتك وسياساتك وأسئلتك الشائعة، فيرد البوت بمعلومات عملك أنت تحديداً، لا إجابات عامة.', 'M12 6.04A8.97 8.97 0 0 0 6 3.75c-1.05 0-2.06.18-3 .51v14.25A8.99 8.99 0 0 1 6 18c2.3 0 4.41.87 6 2.29m0-14.25a8.97 8.97 0 0 1 6-2.29c1.05 0 2.06.18 3 .51v14.25A8.99 8.99 0 0 0 18 18a8.97 8.97 0 0 0-6 2.29m0-14.25v14.25', 'gold'],
            ['تعدد نماذج الذكاء', 'اختر النموذج الأنسب: Gemini أو ChatGPT أو DeepSeek — وبدّل بينها في أي وقت دون تغيير شيء.', 'M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104a24.3 24.3 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3', 'emerald'],
            ['لوحة تحكم وتحليلات', 'تابع المحادثات والاستهلاك والتكلفة لحظياً من لوحة عربية أنيقة وسهلة على الجوال والحاسب.', 'M3 13.5 12 4l9 9.5M5 12v7a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-7', 'night'],
            ['مختبر تجربة فوري', 'جرّب بوتك حيّاً قبل ربط الرقم — اكتب رسالة وشاهد الرد لحظياً للتأكد من جودته.', 'M9.8 15.9 9 18.75l-.8-2.85a4.5 4.5 0 0 0-3.1-3.1L2.25 12l2.85-.8a4.5 4.5 0 0 0 3.1-3.1L9 5.25l.8 2.85a4.5 4.5 0 0 0 3.1 3.1L15.75 12l-2.85.8a4.5 4.5 0 0 0-3.1 3.1Z', 'gold'],
            ['أمان وعزل تام', 'بيانات كل عميل معزولة ومشفّرة بالكامل، عبر واتساب Cloud API الرسمي من Meta — بأعلى معايير الأمان.', 'M11.99 3 4.5 6v6c0 4.2 3.2 7.6 7.5 9 4.3-1.4 7.5-4.8 7.5-9V6L11.99 3Z', 'emerald'],
        ] as [$title, $desc, $icon, $color])
            @php $iconClass = ['emerald' => 'bg-emerald/10 text-emerald', 'gold' => 'bg-gold/10 text-gold', 'night' => 'bg-night/10 text-night'][$color] ?? 'bg-emerald/10 text-emerald'; @endphp
            <div class="rounded-2xl border border-ink/10 bg-white p-6 shadow-luxe transition hover:-translate-y-1 hover:shadow-luxe-lg">
                <span class="grid h-12 w-12 place-items-center rounded-xl {{ $iconClass }}">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icon }}"/></svg>
                </span>
                <h3 class="mt-5 text-lg font-bold">{{ $title }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-ink-soft">{{ $desc }}</p>
            </div>
        @endforeach
    </div>
</section>

<!-- ===== How it works ===== -->
<section id="how" class="border-y border-ink/10 bg-white">
    <div class="mx-auto max-w-6xl px-5 py-20">
        <div class="mx-auto max-w-2xl text-center">
            <span class="font-mono text-xs font-semibold uppercase tracking-widest text-emerald">كيف يعمل</span>
            <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">من التسجيل إلى أول رد في دقائق</h2>
        </div>
        <div class="mt-12 grid gap-6 md:grid-cols-4">
            @foreach ([
                ['١', 'أنشئ حسابك', 'سجّل مجاناً في دقيقة واحدة وادخل لوحة التحكم.'],
                ['٢', 'اربط رقم واتساب', 'اربط رقم عملك عبر واتساب Cloud API الرسمي بأمان.'],
                ['٣', 'أضف معرفتك', 'أدخل منتجاتك وسياساتك وحدّد شخصية بوتك.'],
                ['٤', 'البوت يرد آلياً', 'يبدأ بوتك بالرد على عملائك فوراً، على مدار الساعة.'],
            ] as [$n, $t, $d])
                <div class="relative rounded-2xl border border-ink/10 bg-paper p-6">
                    <span class="grid h-11 w-11 place-items-center rounded-full bg-emerald font-bold text-white">{{ $n }}</span>
                    <h3 class="mt-4 font-bold">{{ $t }}</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-ink-soft">{{ $d }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>

<!-- ===== Providers ===== -->
<section id="providers" class="mx-auto max-w-6xl px-5 py-20 text-center">
    <span class="font-mono text-xs font-semibold uppercase tracking-widest text-emerald">النماذج المدعومة</span>
    <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">أقوى نماذج الذكاء الاصطناعي، باختيارك</h2>
    <p class="mx-auto mt-4 max-w-xl text-ink-soft">بدّل بين النماذج لكل عميل حسب الحاجة — جودة أعلى أو تكلفة أقل، القرار لك.</p>
    <div class="mx-auto mt-10 grid max-w-3xl gap-4 sm:grid-cols-3">
        @foreach ([
            ['Gemini', 'Google', 'سريع واقتصادي وقوي بالعربية'],
            ['ChatGPT', 'OpenAI', 'الأشهر والأقوى في الفهم العام'],
            ['DeepSeek', 'DeepSeek', 'أداء عالٍ بتكلفة منخفضة'],
        ] as [$name, $by, $note])
            <div class="rounded-2xl border border-ink/10 bg-white p-6 shadow-luxe">
                <div class="text-xl font-bold text-ink">{{ $name }}</div>
                <div class="mt-0.5 font-mono text-[11px] uppercase tracking-wider text-emerald">{{ $by }}</div>
                <p class="mt-3 text-sm text-ink-soft">{{ $note }}</p>
            </div>
        @endforeach
    </div>
</section>

<!-- ===== Use cases ===== -->
<section class="border-y border-ink/10 bg-white">
    <div class="mx-auto max-w-6xl px-5 py-16">
        <h2 class="text-center text-2xl font-bold tracking-tight sm:text-3xl">مناسب لكل عمل يتلقّى رسائل واتساب</h2>
        <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
            @foreach (['المتاجر الإلكترونية', 'المطاعم والكافيهات', 'العيادات والمراكز', 'مقدّمو الخدمات', 'العقارات', 'التعليم والتدريب', 'السياحة والسفر'] as $u)
                <span class="rounded-full border border-ink/10 bg-paper px-5 py-2.5 text-sm font-medium text-ink-2">{{ $u }}</span>
            @endforeach
        </div>
    </div>
</section>

<!-- ===== FAQ ===== -->
<section id="faq" class="mx-auto max-w-3xl px-5 py-20">
    <div class="text-center">
        <span class="font-mono text-xs font-semibold uppercase tracking-widest text-emerald">الأسئلة الشائعة</span>
        <h2 class="mt-3 text-3xl font-bold tracking-tight sm:text-4xl">أسئلة قد تدور في ذهنك</h2>
    </div>
    <div class="mt-10 space-y-3" x-data="{ open: 0 }">
        @foreach ([
            ['هل أحتاج خبرة تقنية؟', 'لا إطلاقاً. كل شيء يُدار من لوحة تحكم عربية بسيطة — تسجّل، تربط رقمك، وتضيف معلوماتك بخطوات واضحة.'],
            ['هل يستخدم رقم واتساب الخاص بي؟', 'نعم. يرد البوت من رقم عملك أنت عبر واتساب Cloud API الرسمي من Meta — لا أرقام مشتركة.'],
            ['هل بياناتي آمنة؟', 'بالكامل. بيانات كل عميل معزولة ومشفّرة، والمفاتيح محمية، ولا يصل أحد لبيانات عمل آخر.'],
            ['كم تكلفة الردود؟', 'ردود واتساب داخل نافذة الـ24 ساعة مجانية من Meta، وتكلفة الذكاء الاصطناعي محسوبة وشفافة في لوحتك.'],
            ['متى يبدأ بوتي بالعمل؟', 'فور موافقة الإدارة على حسابك وربط رقمك وإضافة معرفتك — عادةً خلال دقائق.'],
        ] as $i => [$q, $a])
            <div class="overflow-hidden rounded-2xl border border-ink/10 bg-white">
                <button type="button" @click="open === {{ $i }} ? open = null : open = {{ $i }}" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-right">
                    <span class="font-semibold text-ink">{{ $q }}</span>
                    <svg class="h-5 w-5 shrink-0 text-emerald transition-transform" :class="open === {{ $i }} && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                </button>
                <div x-show="open === {{ $i }}" x-transition style="display:none">
                    <p class="px-5 pb-5 text-sm leading-relaxed text-ink-soft">{{ $a }}</p>
                </div>
            </div>
        @endforeach
    </div>
</section>

<!-- ===== Final CTA ===== -->
<section class="mx-auto max-w-6xl px-5 pb-20">
    <div class="relative overflow-hidden rounded-3xl bg-night px-6 py-16 text-center text-white">
        <div class="pointer-events-none absolute inset-0 opacity-70" style="background:radial-gradient(700px 360px at 50% -20%, rgba(22,200,146,.22), transparent 60%);"></div>
        <div class="relative">
            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">جاهز لأتمتة خدمة عملائك؟</h2>
            <p class="mx-auto mt-4 max-w-xl text-white/70">انضم اليوم وابدأ ببوت واتساب ذكي يعمل نيابةً عنك على مدار الساعة.</p>
            <div class="mt-8">
                @auth
                    <a href="{{ $homeRoute }}" class="inline-block rounded-xl bg-emerald px-8 py-3.5 font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">اذهب إلى لوحة التحكم</a>
                @else
                    <a href="{{ route('register') }}" class="inline-block rounded-xl bg-emerald px-8 py-3.5 font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">أنشئ حسابك مجاناً</a>
                @endauth
            </div>
        </div>
    </div>
</section>

<!-- ===== Footer ===== -->
<footer class="border-t border-ink/10 bg-white">
    <div class="mx-auto max-w-6xl px-5 py-12">
        <div class="flex flex-col gap-8 sm:flex-row sm:justify-between">
            <div class="max-w-sm">
                <div class="flex items-center gap-2 text-lg font-bold">
                    <span class="grid h-9 w-9 place-items-center rounded-xl bg-emerald/10 text-emerald">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5l1.2-3.6A8.25 8.25 0 1 1 7.5 21Z"/></svg>
                    </span>
                    وريد
                </div>
                <p class="mt-3 text-sm leading-relaxed text-ink-soft">منصة بوتات واتساب الذكية للأعمال — رد آلي بالذكاء الاصطناعي على مدار الساعة، بأسلوب علامتك التجارية.</p>
            </div>
            <div class="grid grid-cols-2 gap-8 text-sm sm:gap-14">
                <div>
                    <div class="font-mono text-xs font-semibold uppercase tracking-wider text-ink-soft">المنصّة</div>
                    <ul class="mt-3 space-y-2 text-ink-2">
                        <li><a href="#features" class="transition hover:text-emerald">المزايا</a></li>
                        <li><a href="#how" class="transition hover:text-emerald">كيف يعمل</a></li>
                        <li><a href="#faq" class="transition hover:text-emerald">الأسئلة الشائعة</a></li>
                    </ul>
                </div>
                <div>
                    <div class="font-mono text-xs font-semibold uppercase tracking-wider text-ink-soft">الحساب</div>
                    <ul class="mt-3 space-y-2 text-ink-2">
                        <li><a href="{{ route('login') }}" class="transition hover:text-emerald">تسجيل الدخول</a></li>
                        <li><a href="{{ route('register') }}" class="transition hover:text-emerald">إنشاء حساب</a></li>
                        <li><a href="mailto:info@m.wareed.vip" class="transition hover:text-emerald">تواصل معنا</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="mt-10 flex flex-col items-center justify-between gap-3 border-t border-ink/10 pt-6 text-xs text-ink-soft sm:flex-row">
            <span>© {{ date('Y') }} وريد. جميع الحقوق محفوظة.</span>
            <span class="font-mono">m.wareed.vip · info@m.wareed.vip</span>
        </div>
    </div>
</footer>

</body>
</html>
