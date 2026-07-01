<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-bold text-ink">دليل الربط خطوة بخطوة</h1>
                <p class="text-sm text-ink-soft">مُعَدّ لمن لا خبرة تقنية لديه — اتبع الخطوات بالترتيب من البداية للنهاية.</p>
            </div>
            <a
                href="{{ route('whatsapp.edit') }}"
                class="inline-flex items-center gap-2 rounded-xl border border-ink/10 bg-white px-4 py-2.5 text-sm font-semibold text-ink-2 shadow-luxe transition hover:bg-paper"
            >
                <svg class="h-5 w-5 text-emerald" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5l1.2-3.6A8.25 8.25 0 1 1 7.5 21Z" />
                </svg>
                <span>الرجوع لصفحة ربط واتساب</span>
            </a>
        </div>
    </x-slot>

    @php
        $steps = [
            [
                'id' => 'step-0',
                'kicker' => 'قبل أن تبدأ',
                'title' => 'تجهيزات أولية',
                'body' => [
                    'حساب فيسبوك شخصي (يكفي حساب عادي — لا داعٍ لصفحة تجارية الآن).',
                    'رقم هاتف تستطيع استقبال رسالة SMS أو مكالمة عليه للتحقق — ويُفضَّل ألا يكون مسجَّلاً حالياً على تطبيق واتساب العادي.',
                ],
                'tip' => 'إن كان الرقم مسجَّلاً بالفعل على واتساب العادي (الشخصي)، عليك حذف حسابه من تطبيق واتساب أولاً قبل ربطه هنا — وإلا سيرفضه Meta أو يسبب تعارضاً في الرسائل.',
                'tipTitle' => 'تحذير مهم',
                'tipTone' => 'warning',
                'link' => null,
            ],
            [
                'id' => 'step-1',
                'kicker' => 'الخطوة 1 من 8',
                'title' => 'أنشئ حساب Meta Business',
                'body' => [
                    'افتح business.facebook.com وسجّل الدخول بحساب فيسبوك الشخصي.',
                    'اضغط Create Account واملأ اسم نشاطك التجاري وبريدك الإلكتروني.',
                    '(لاحقاً عند التشغيل التجاري الفعلي: أكمل التحقق من النشاط من Security Center داخل نفس الحساب — لا حاجة له أثناء التجربة).',
                ],
                'tip' => 'استخدم الاسم الرسمي لنشاطك التجاري — هذا الاسم قد يظهر لاحقاً في واجهة Meta عند التحقق.',
                'tipTitle' => 'أين أجدها بالضبط؟',
                'tipTone' => 'info',
                'link' => ['label' => 'business.facebook.com', 'url' => 'https://business.facebook.com'],
            ],
            [
                'id' => 'step-2',
                'kicker' => 'الخطوة 2 من 8',
                'title' => 'أنشئ تطبيق Meta',
                'body' => [
                    'افتح developers.facebook.com وسجّل الدخول بنفس حساب فيسبوك.',
                    'من القائمة اضغط My Apps ← Create App.',
                    'اختر نوع التطبيق Business ثم أكمل الخطوات.',
                    'بعد إنشاء التطبيق، ابحث عن منتج WhatsApp في قائمة المنتجات واضغط Set up لإضافته.',
                ],
                'tip' => 'صفحة My Apps تعرض كل تطبيقاتك — تطبيقك الجديد سيظهر ببطاقة باسمه؛ اضغط عليها للدخول للوحة التحكم الخاصة به.',
                'tipTitle' => 'أين أجدها بالضبط؟',
                'tipTone' => 'info',
                'link' => ['label' => 'developers.facebook.com', 'url' => 'https://developers.facebook.com'],
            ],
            [
                'id' => 'step-3',
                'kicker' => 'الخطوة 3 من 8',
                'title' => 'اربط رقم هاتفك',
                'body' => [
                    'من قائمة تطبيقك يساراً، افتح WhatsApp ← API Setup.',
                    'للتجربة السريعة: يمكنك استخدام رقم الاختبار المجاني الذي توفّره Meta تلقائياً (يرسل فقط لأرقام مضافة يدوياً كمستلمين تجريبيين).',
                    'للرقم الحقيقي: اضغط Add phone number، أدخل رقمك، واختر التحقق عبر SMS أو مكالمة صوتية، ثم أدخل الرمز الذي يصلك.',
                ],
                'tip' => 'إن كنت تجهّز الإطلاق الفعلي لعملك، أضف رقمك الحقيقي مباشرة بدل رقم الاختبار حتى لا تُضطر لإعادة الربط لاحقاً.',
                'tipTitle' => 'نصيحة',
                'tipTone' => 'info',
                'link' => null,
            ],
            [
                'id' => 'step-4',
                'kicker' => 'الخطوة 4 من 8',
                'title' => 'انسخ Phone Number ID و WABA ID',
                'body' => [
                    'ابقَ في نفس صفحة WhatsApp ← API Setup.',
                    'ستجد Phone number ID معروضاً أسفل حقل رقم الهاتف مباشرة.',
                    'وستجد WhatsApp Business Account ID (يُختصر WABA ID) في أعلى الصفحة نفسها.',
                    'انسخ القيمتين واحتفظ بهما — ستلصقهما في الخطوة الأخيرة داخل هذه المنصّة.',
                ],
                'tip' => 'كلا المعرّفين أرقام طويلة فقط (بدون حروف أو رموز) — إن رأيت حروفاً فأنت في حقل غير صحيح.',
                'tipTitle' => 'أين أجدها بالضبط؟',
                'tipTone' => 'info',
                'link' => null,
            ],
            [
                'id' => 'step-5',
                'kicker' => 'الخطوة 5 من 8',
                'title' => 'أصدِر التوكن (Access Token)',
                'body' => [
                    'للتجربة السريعة: في نفس صفحة API Setup يوجد توكن مؤقّت صالح 24 ساعة فقط — انسخه لتجربة سريعة.',
                    'للاستخدام الدائم (الموصى به): من قائمة تطبيقك اذهب إلى Business Settings ← Users ← System Users.',
                    'اضغط Add، أعطِ المستخدم اسماً، واختر له دور Admin.',
                    'اضغط على المستخدم الذي أنشأته ثم Generate new token، اختر تطبيقك من القائمة.',
                    'فعّل صلاحيتَي whatsapp_business_messaging و whatsapp_business_management، ثم اضغط Generate token.',
                    'انسخ التوكن فوراً — يظهر مرة واحدة فقط ولن تتمكن من رؤيته مجدداً بعد إغلاق النافذة.',
                ],
                'tip' => 'إن أغلقت النافذة قبل النسخ، لا مشكلة: عد لنفس المستخدم واضغط Generate new token لإصدار توكن جديد.',
                'tipTitle' => 'أين أجدها بالضبط؟',
                'tipTone' => 'info',
                'link' => ['label' => 'Business Settings', 'url' => 'https://business.facebook.com/settings'],
            ],
            [
                'id' => 'step-6',
                'kicker' => 'الخطوة 6 من 8',
                'title' => 'انسخ سرّ التطبيق (App Secret)',
                'body' => [
                    'من قائمة تطبيقك يساراً، افتح App Settings ← Basic.',
                    'ابحث عن حقل App Secret، ثم اضغط الزر Show بجانبه (قد يطلب منك تأكيد كلمة مرور فيسبوك).',
                    'انسخ القيمة الظاهرة.',
                ],
                'tip' => 'نستخدم هذا السرّ للتأكد أن كل رسالة تصل من واتساب فعلاً من تطبيقك أنت، لا من طرف آخر يحاول انتحال الاتصال — لهذا لا نعرض القيمة المحفوظة مرة أخرى بعد حفظها.',
                'tipTitle' => 'أين أجدها بالضبط؟ ولماذا نطلبها؟',
                'tipTone' => 'info',
                'link' => null,
            ],
            [
                'id' => 'step-7',
                'kicker' => 'الخطوة 7 من 8',
                'title' => 'اضبط الـ Webhook داخل تطبيق Meta',
                'body' => [
                    'من قائمة تطبيقك يساراً، افتح WhatsApp ← Configuration واضغط Edit بجانب Webhook.',
                    'الصق Callback URL من الصندوق أدناه (زر النسخ جاهز).',
                    'الصق Verify token من الصندوق أدناه (زر النسخ جاهز).',
                    'اضغط Verify and Save — إن ظهرت علامة صح فالربط الأولي نجح.',
                    'بعد الحفظ اضغط Manage بجانب Webhook fields، وفعّل الاشتراك في حقل messages فقط (هذا ما يوصل رسائل عملائك لبوتك).',
                ],
                'tip' => 'إن ظهر خطأ عند Verify and Save، تأكّد أن المنصّة (موقعنا) منشورة وتعمل فعلياً على الإنترنت قبل إعادة المحاولة.',
                'tipTitle' => 'نصيحة',
                'tipTone' => 'info',
                'link' => null,
                'copy' => true,
            ],
            [
                'id' => 'step-8',
                'kicker' => 'الخطوة 8 من 8 — الأخيرة',
                'title' => 'الصق البيانات هنا في المنصّة واحفظ',
                'body' => [
                    'ارجع لصفحة «ربط واتساب» في هذه المنصّة (زر أعلى هذه الصفحة).',
                    'الصق القيم الأربع: Phone number ID · WABA ID · Access Token · App Secret.',
                    'اضغط «حفظ بيانات الربط».',
                    'اضغط «تحقّق من الاتصال» للتأكد أن Meta تقبل البيانات.',
                    'أخيراً اضغط «رسالة تجريبية» لإرسال رسالة حقيقية لرقمك والتأكد أن كل شيء يعمل من البداية للنهاية.',
                ],
                'tip' => 'إن نجحت الرسالة التجريبية، فبوتك جاهز للرد التلقائي على عملائك فوراً.',
                'tipTitle' => 'علامة النجاح',
                'tipTone' => 'success',
                'link' => null,
            ],
        ];
    @endphp

    <div class="space-y-6" x-data="{ activeStep: 0, total: {{ count($steps) }} }">
        {{-- Progress indicator --}}
        <x-card>
            <div class="flex items-center justify-between gap-4">
                <div class="flex items-center gap-2 overflow-x-auto pb-1">
                    @foreach ($steps as $i => $step)
                        <button
                            type="button"
                            @click="activeStep = {{ $i }}; document.getElementById('{{ $step['id'] }}').scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-bold transition"
                            :class="activeStep === {{ $i }} ? 'bg-emerald text-white shadow-luxe' : 'bg-paper text-ink-soft hover:bg-emerald/10 hover:text-emerald-deep'"
                        >
                            {{ $i }}
                        </button>
                    @endforeach
                </div>
                <span class="hidden shrink-0 text-sm font-semibold text-ink-soft sm:block">
                    <span x-text="activeStep + 1"></span> / <span x-text="total"></span>
                </span>
            </div>
            <div class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-paper">
                <div
                    class="h-full rounded-full bg-gradient-to-l from-emerald to-signal transition-all duration-500"
                    :style="`width: ${((activeStep + 1) / total) * 100}%`"
                ></div>
            </div>
        </x-card>

        @foreach ($steps as $i => $step)
            @php
                $toneClasses = match ($step['tipTone']) {
                    'warning' => 'border-gold/40 bg-gold/10 text-ink-2',
                    'success' => 'border-emerald/30 bg-emerald/10 text-emerald-deep',
                    default => 'border-emerald/20 bg-emerald/5 text-ink-2',
                };
                $iconTone = match ($step['tipTone']) {
                    'warning' => 'text-gold',
                    'success' => 'text-emerald-deep',
                    default => 'text-emerald',
                };
            @endphp
            <div id="{{ $step['id'] }}" class="scroll-mt-24">
                <x-card>
                    <div class="flex flex-col gap-5 sm:flex-row sm:gap-6">
                        <div class="flex shrink-0 sm:flex-col sm:items-center sm:gap-2">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-night-gradient text-lg font-bold text-white shadow-luxe">
                                {{ $i }}
                            </span>
                        </div>

                        <div class="min-w-0 flex-1 space-y-4">
                            <div>
                                <span class="text-xs font-bold uppercase tracking-wide text-emerald">{{ $step['kicker'] }}</span>
                                <h2 class="mt-1 text-lg font-bold text-ink">{{ $step['title'] }}</h2>
                            </div>

                            <ol class="space-y-2.5">
                                @foreach ($step['body'] as $j => $line)
                                    <li class="flex gap-3 text-sm leading-relaxed text-ink-2">
                                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-paper text-[11px] font-bold text-ink-soft">{{ $j + 1 }}</span>
                                        <span>{!! $line !!}</span>
                                    </li>
                                @endforeach
                            </ol>

                            @if ($step['tip'])
                                <div class="flex items-start gap-3 rounded-xl border {{ $toneClasses }} px-4 py-3 text-sm">
                                    <svg class="mt-0.5 h-5 w-5 shrink-0 {{ $iconTone }}" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 0 0 6-6c0-1.887-.454-3.665-1.257-5.234M12 18.75a6 6 0 0 1-6-6c0-1.887.454-3.665 1.257-5.234m9.486 0A5.987 5.987 0 0 0 12 5.25a5.987 5.987 0 0 0-4.486 2.016M12 18.75V21m-3.75-3-.5.5m8.5-.5.5.5" />
                                    </svg>
                                    <p><span class="font-bold">{{ $step['tipTitle'] }}:</span> {{ $step['tip'] }}</p>
                                </div>
                            @endif

                            @if (($step['copy'] ?? false) === true)
                                <div class="space-y-4 rounded-xl border border-ink/10 bg-paper p-4">
                                    <x-copy-field label="Callback URL" :value="$callbackUrl" />
                                    <x-copy-field label="Verify token" :value="$verifyToken" />
                                </div>
                            @endif

                            @if ($step['link'])
                                <a
                                    href="{{ $step['link']['url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex items-center gap-2 text-sm font-semibold text-emerald hover:text-emerald-deep"
                                >
                                    <span>افتح {{ $step['link']['label'] }}</span>
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            @endif
                        </div>
                    </div>
                </x-card>
            </div>
        @endforeach

        {{-- Done — what's next --}}
        <x-card>
            <div class="flex flex-col items-start gap-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-4">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-emerald/10 text-2xl">🎉</span>
                    <div>
                        <h2 class="text-base font-bold text-ink">تمّ! ماذا بعد؟</h2>
                        <p class="mt-1.5 max-w-xl text-sm leading-relaxed text-ink-2">
                            بمجرد نجاح «رسالة تجريبية»، بوتك يبدأ بالرد التلقائي على عملائك فور وصول أي رسالة واتساب لرقمك.
                            راجِع بعدها إعداد البوت وقاعدة المعرفة لتخصيص ردوده، و
                            <span class="font-mono font-semibold">docs/META_REQUIREMENTS.md</span>
                            قبل أي إطلاق تجاري فعلي (متطلبات مراجعة Meta).
                        </p>
                    </div>
                </div>
                <a
                    href="{{ route('whatsapp.edit') }}"
                    class="inline-flex shrink-0 items-center gap-2 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep"
                >
                    <span>الذهاب لصفحة ربط واتساب</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                </a>
            </div>
        </x-card>
    </div>
</x-app-layout>
