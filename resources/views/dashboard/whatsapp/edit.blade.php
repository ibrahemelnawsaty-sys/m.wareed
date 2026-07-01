<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-lg font-bold text-ink">ربط واتساب</h1>
                <p class="text-sm text-ink-soft">اربط رقم عملك عبر WhatsApp Cloud API الرسمي.</p>
            </div>
            <a
                href="{{ route('whatsapp.guide') }}"
                class="inline-flex items-center gap-2 rounded-xl border border-gold/40 bg-gold/10 px-4 py-2.5 text-sm font-semibold text-ink-2 shadow-luxe transition hover:bg-gold/15"
            >
                <svg class="h-5 w-5 text-gold" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
                <span>لا تعرف من أين تبدأ؟ دليل الربط خطوة بخطوة</span>
            </a>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Success banners (§10 — confirm the action the owner just took). --}}
        @if (session('status') === 'whatsapp-updated')
            <div class="flex items-center gap-3 rounded-xl border border-emerald/30 bg-emerald/10 px-4 py-3 text-sm text-emerald-deep">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span>تم حفظ بيانات الربط بنجاح.</span>
            </div>
        @endif

        @if (session('status') === 'whatsapp-test-sent')
            <div class="flex items-center gap-3 rounded-xl border border-emerald/30 bg-emerald/10 px-4 py-3 text-sm text-emerald-deep">
                <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                </svg>
                <span>أُرسلت رسالة <span class="font-mono font-semibold">hello_world</span> التجريبية؛ تحقّق من واتساب الرقم الذي أدخلته.</span>
            </div>
        @endif

        {{-- Meta setup guide — numbered, collapsible (Alpine). --}}
        <x-card title="دليل إعداد Meta" subtitle="اتبع الخطوات بالترتيب لإنشاء التطبيق وربط الرقم ثم التحقق من الاتصال.">
            <div x-data="{ open: true }">
                <button
                    type="button"
                    @click="open = !open"
                    class="flex w-full items-center justify-between rounded-xl border border-ink/10 bg-paper px-4 py-3 text-sm font-semibold text-ink-2 transition hover:bg-white"
                >
                    <span>عرض الخطوات الست</span>
                    <svg class="h-5 w-5 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </button>

                <ol x-show="open" x-transition.opacity class="mt-5 space-y-4">
                    @foreach ([
                        'أنشئ <span class="font-semibold">Meta Business Portfolio</span> باسم شركتك القانوني، وأكمل <span class="font-semibold">التحقق التجاري</span> (Business Verification).',
                        'أنشئ <span class="font-semibold">Meta App</span> من نوع <span class="font-semibold">Business</span>، ثم أضِف منتج <span class="font-semibold">WhatsApp</span> إليه.',
                        'من إعدادات WhatsApp انسخ <span class="font-mono font-semibold">phone_number_id</span> و<span class="font-mono font-semibold">waba_id</span>.',
                        'أنشئ <span class="font-semibold">System User</span> ومنه أصدِر <span class="font-semibold">توكناً دائماً</span> بصلاحيات whatsapp_business_messaging و whatsapp_business_management.',
                        'اضبط الـ Webhook في تطبيق Meta: الصق <span class="font-semibold">Callback URL</span> و<span class="font-semibold">Verify token</span> الظاهرين في بطاقة «إعدادات الـ Webhook» أدناه.',
                        'الصق البيانات في «بيانات الرقم» واحفظها، ثم اضغط <span class="font-semibold">«تحقّق من الاتصال»</span>، وأخيراً أرسِل رسالة تجريبية.',
                    ] as $i => $step)
                        <li class="flex gap-3">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald/10 text-sm font-bold text-emerald-deep">{{ $i + 1 }}</span>
                            <p class="pt-0.5 text-sm leading-relaxed text-ink-2">{!! $step !!}</p>
                        </li>
                    @endforeach
                </ol>

                <p class="mt-5 text-xs text-ink-soft">
                    للمتطلبات الكاملة والمستندات المطلوبة من Meta، راجِع
                    <span class="font-mono font-semibold">docs/META_REQUIREMENTS.md</span>.
                </p>
            </div>
        </x-card>

        <!-- Read-only integration values -->
        <x-card title="إعدادات الـ Webhook" subtitle="انسخ هذه القيم والصقها في إعدادات تطبيقك على منصة Meta للمطوّرين.">
            <div class="space-y-5">
                <x-copy-field label="Callback URL" :value="$callbackUrl" />
                <x-copy-field label="Verify token" :value="$verifyToken" />

                <div class="flex items-start gap-3 rounded-xl border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-ink-2">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-gold" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <p>
                        الصق نفس <span class="font-mono font-semibold">Callback URL</span> و<span class="font-mono font-semibold">Verify token</span> أعلاه في تبويب
                        <span class="font-semibold">WhatsApp → Configuration</span> بتطبيقك على Meta، ثم اضغط <span class="font-semibold">Verify and Save</span>.
                        لا تعرف مكانها بالضبط؟ افتح
                        <a href="{{ route('whatsapp.guide') }}" class="font-semibold text-emerald underline decoration-emerald/40 underline-offset-2 hover:text-emerald-deep">دليل الربط خطوة بخطوة</a>.
                    </p>
                </div>
            </div>
        </x-card>

        <!-- Editable connection form -->
        <x-card title="بيانات الرقم" subtitle="املأ بيانات الرقم والتوكن كما تظهر في لوحة WhatsApp Cloud API.">
            <form method="POST" action="{{ route('whatsapp.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="display_name" :value="'الاسم الظاهر'" />
                    <x-text-input id="display_name" name="display_name" type="text" class="mt-1.5 block w-full" :value="old('display_name', $account->display_name)" placeholder="اسم العمل كما يظهر للعملاء" />
                    <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="phone_number_id" :value="'Phone Number ID'" />
                        <x-text-input id="phone_number_id" name="phone_number_id" type="text" dir="ltr" class="mt-1.5 block w-full font-mono" :value="old('phone_number_id', $account->phone_number_id)" placeholder="123456789012345" />
                        <x-input-error :messages="$errors->get('phone_number_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="waba_id" :value="'WABA ID'" />
                        <x-text-input id="waba_id" name="waba_id" type="text" dir="ltr" class="mt-1.5 block w-full font-mono" :value="old('waba_id', $account->waba_id)" placeholder="123456789012345" />
                        <x-input-error :messages="$errors->get('waba_id')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="access_token" :value="'Access Token'" />
                    <x-text-input
                        id="access_token"
                        name="access_token"
                        type="password"
                        dir="ltr"
                        autocomplete="off"
                        class="mt-1.5 block w-full font-mono"
                        :placeholder="$hasAccessToken ? '••••••••••  محفوظ' : 'الصق التوكن هنا'"
                    />
                    <x-input-error :messages="$errors->get('access_token')" class="mt-2" />
                    <p class="mt-2 text-xs text-ink-soft">
                        @if ($hasAccessToken)
                            توكن محفوظ ومشفّر بالفعل. اتركه فارغاً للإبقاء عليه، أو أدخل توكناً جديداً لاستبداله.
                        @else
                            يُخزَّن التوكن مشفّراً ولا يُعرض مرة أخرى بعد الحفظ.
                        @endif
                    </p>
                </div>

                <div>
                    <div class="flex items-center justify-between">
                        <x-input-label for="app_secret" :value="'سرّ التطبيق (App Secret)'" />
                        @if ($hasAppSecret)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-deep">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                مضبوط
                            </span>
                        @endif
                    </div>
                    <x-text-input
                        id="app_secret"
                        name="app_secret"
                        type="password"
                        dir="ltr"
                        autocomplete="off"
                        class="mt-1.5 block w-full font-mono"
                        :placeholder="$hasAppSecret ? '••••••••••  محفوظ' : 'الصق سرّ التطبيق هنا'"
                    />
                    <x-input-error :messages="$errors->get('app_secret')" class="mt-2" />
                    <p class="mt-2 text-xs text-ink-soft">
                        من إعدادات تطبيقك على Meta:
                        <span class="font-semibold">App Settings → Basic</span>، حقل
                        <span class="font-mono font-semibold">App Secret</span> ← اضغط <span class="font-semibold">Show</span>.
                        نستخدمه للتأكد أن الرسائل الواردة قادمة فعلاً من تطبيقك.
                        @if ($hasAppSecret)
                            اتركه فارغاً للإبقاء على القيمة المحفوظة.
                        @endif
                        <a href="{{ route('whatsapp.guide') }}#step-6" class="font-semibold text-emerald underline decoration-emerald/40 underline-offset-2 hover:text-emerald-deep">أين أجده بالضبط؟</a>
                    </p>
                </div>

                <div class="flex justify-end border-t border-ink/10 pt-5">
                    <x-primary-button>حفظ بيانات الربط</x-primary-button>
                </div>
            </form>
        </x-card>

        {{-- Connection status — probe Meta for the live number status (§10). --}}
        <x-card title="حالة الاتصال" subtitle="تأكّد من أن البيانات المحفوظة صحيحة قبل التشغيل الحيّ.">
            <div class="space-y-5">
                <x-input-error :messages="$errors->get('connection')" />

                @php($info = session('connection_status'))
                @if (is_array($info))
                    @php($rating = $info['quality_rating'] ?? '')
                    @php($ratingTone = match (strtoupper((string) $rating)) {
                        'GREEN' => 'border-emerald/30 bg-emerald/10 text-emerald-deep',
                        'YELLOW' => 'border-gold/40 bg-gold/10 text-ink-2',
                        'RED' => 'border-red-300 bg-red-50 text-red-700',
                        default => 'border-ink/15 bg-paper text-ink-soft',
                    })
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-xl border border-ink/10 bg-paper px-4 py-3">
                            <span class="block text-xs font-semibold text-ink-soft">الاسم الموثّق</span>
                            <span class="mt-1 block text-sm font-semibold text-ink-2">{{ $info['verified_name'] !== '' ? $info['verified_name'] : '—' }}</span>
                        </div>
                        <div class="rounded-xl border border-ink/10 bg-paper px-4 py-3">
                            <span class="block text-xs font-semibold text-ink-soft">رقم العرض</span>
                            <span class="mt-1 block font-mono text-sm text-ink-2" dir="ltr">{{ $info['display_phone_number'] !== '' ? $info['display_phone_number'] : '—' }}</span>
                        </div>
                        <div class="rounded-xl border {{ $ratingTone }} px-4 py-3">
                            <span class="block text-xs font-semibold opacity-80">تقييم الجودة</span>
                            <span class="mt-1 block text-sm font-bold">{{ $rating !== '' ? $rating : '—' }}</span>
                        </div>
                        <div class="rounded-xl border border-ink/10 bg-paper px-4 py-3">
                            <span class="block text-xs font-semibold text-ink-soft">حالة التحقق</span>
                            <span class="mt-1 block text-sm font-semibold text-ink-2">{{ ($info['code_verification_status'] ?? '') !== '' ? $info['code_verification_status'] : '—' }}</span>
                        </div>
                    </div>
                @else
                    <p class="text-sm text-ink-soft">
                        اضغط الزر لاستعلام Meta والتأكّد من أن الرقم والتوكن صحيحان.
                        @unless ($connectionReady)
                            <span class="font-semibold text-ink-2">احفظ رقم الهاتف والتوكن أولاً.</span>
                        @endunless
                    </p>
                @endif

                <form method="POST" action="{{ route('whatsapp.verify') }}">
                    @csrf
                    <x-primary-button>تحقّق من الاتصال</x-primary-button>
                </form>
            </div>
        </x-card>

        {{-- Test message — send a pre-approved hello_world template (§11). --}}
        <x-card title="رسالة تجريبية" subtitle="أرسِل قالب hello_world المعتمد إلى رقمك للتأكد من سريان الربط من النهاية للنهاية.">
            <form method="POST" action="{{ route('whatsapp.test') }}" class="space-y-5">
                @csrf

                <x-input-error :messages="$errors->get('test')" />

                <div>
                    <x-input-label for="to" :value="'رقم الوجهة'" />
                    <x-text-input id="to" name="to" type="text" dir="ltr" inputmode="numeric" class="mt-1.5 block w-full font-mono" :value="old('to')" placeholder="9665XXXXXXXX" />
                    <x-input-error :messages="$errors->get('to')" class="mt-2" />
                    <p class="mt-2 text-xs text-ink-soft">
                        رقمك بصيغة دولية بأرقام فقط بدون <span class="font-mono">+</span> أو مسافات.
                        قالب <span class="font-mono font-semibold">hello_world</span> معتمد ويعمل دون الحاجة لنافذة الـ 24 ساعة.
                    </p>
                </div>

                <div class="flex justify-end border-t border-ink/10 pt-5">
                    <x-primary-button>إرسال رسالة تجريبية</x-primary-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
