<x-admin-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.customers.index') }}" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-ink/10 bg-white text-ink-2 transition hover:bg-paper" aria-label="رجوع">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            </a>
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <h1 class="truncate text-lg font-bold text-ink">{{ $customer->name }}</h1>
                    <x-admin.status-badge :status="$customer->status" />
                </div>
                <p class="text-sm text-ink-soft">تفاصيل العميل وإجراءات الإدارة.</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Validation errors (e.g. invalid subscription months / bot provider) -->
        @if ($errors->any())
            <div class="rounded-xl border border-[#B5462F]/30 bg-[#B5462F]/5 px-4 py-3 text-sm font-medium text-[#B5462F]">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Usage rollup -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">إجمالي الرسائل</p>
                <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($messagesTotal) }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">إجمالي التوكنز</p>
                <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($tokensTotal) }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">إجمالي التكلفة</p>
                <p class="mt-2 text-2xl font-bold text-emerald"><x-cost :micros="$costMicros" /></p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Owner -->
            <x-card title="بيانات المالك">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-soft">الاسم</dt>
                        <dd class="font-semibold text-ink">{{ $owner?->name ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-soft">البريد الإلكتروني</dt>
                        <dd class="font-mono text-xs text-ink-2" dir="ltr">{{ $owner?->email ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-soft">الدور</dt>
                        <dd class="font-semibold text-ink">{{ $owner?->role ?? '—' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-ink-soft">تاريخ الانضمام</dt>
                        <dd class="font-mono text-xs text-ink-2">{{ $customer->created_at?->format('Y-m-d') ?? '—' }}</dd>
                    </div>
                </dl>
            </x-card>

            <!-- WhatsApp account — NO token / key is ever rendered (§13) -->
            <x-card title="حساب واتساب">
                @if ($account)
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-soft">الحالة</dt>
                            <dd><x-admin.status-badge :status="$account->status" /></dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-soft">معرّف الرقم</dt>
                            <dd class="font-mono text-xs text-ink-2" dir="ltr">{{ $account->phone_number_id ?? 'غير مربوط' }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-soft">مزوّد الذكاء</dt>
                            <dd class="font-semibold text-ink">{{ $account->ai_provider }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-soft">النموذج</dt>
                            <dd class="font-mono text-xs text-emerald">{{ $account->ai_model }}</dd>
                        </div>
                        <div class="flex items-center justify-between gap-4">
                            <dt class="text-ink-soft">التوكن / مفتاح API</dt>
                            {{-- §13: secrets are encrypted, hidden, and NEVER printed. We only state presence. --}}
                            <dd class="inline-flex items-center gap-1.5 text-xs font-medium text-ink-soft">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                                {{ filled($account->access_token) ? 'مُخزّن ومشفّر' : 'غير مُدخل' }}
                            </dd>
                        </div>
                    </dl>
                @else
                    <p class="py-4 text-center text-sm text-ink-soft">لا يوجد حساب واتساب لهذا العميل بعد.</p>
                @endif
            </x-card>
        </div>

        <!-- Subscription + lifecycle actions -->
        <x-card title="الاشتراك والحالة" subtitle="إجراءات الإدارة على دورة حياة العميل.">
            <div class="space-y-6">
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-ink/10 bg-paper/50 p-4">
                    <div>
                        <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">نهاية الاشتراك الحالية</p>
                        <p class="mt-1 font-semibold text-ink">
                            @if ($customer->subscription_ends_at)
                                <span @class(['text-[#B5462F]' => $customer->subscription_ends_at->isPast()])>
                                    {{ $customer->subscription_ends_at->format('Y-m-d') }}
                                    @if ($customer->subscription_ends_at->isPast())
                                        <span class="text-xs">(منتهٍ)</span>
                                    @endif
                                </span>
                            @else
                                <span class="text-ink-soft">غير محدّدة</span>
                            @endif
                        </p>
                    </div>

                    <!-- Lifecycle buttons (POST, CSRF, trusted methods, §13) -->
                    <div class="flex flex-wrap items-center gap-2">
                        @if ($customer->status === 'pending')
                            <form method="POST" action="{{ route('admin.customers.approve', $customer->id) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald px-4 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                    موافقة
                                </button>
                            </form>
                        @endif

                        @if ($customer->status === 'suspended')
                            <form method="POST" action="{{ route('admin.customers.unsuspend', $customer->id) }}">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald px-4 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                                    رفع التعليق
                                </button>
                            </form>
                        @endif

                        @if ($customer->status !== 'suspended')
                            <form method="POST" action="{{ route('admin.customers.suspend', $customer->id) }}" onsubmit="return confirm('هل أنت متأكد من تعليق حساب هذا العميل؟ سيتوقّف بوته فوراً.');">
                                @csrf
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl border border-[#B5462F]/30 bg-[#B5462F]/5 px-4 py-2.5 text-sm font-semibold text-[#B5462F] transition hover:bg-[#B5462F]/10">
                                    تعليق
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <!-- Set subscription months -->
                <form method="POST" action="{{ route('admin.customers.subscription', $customer->id) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <x-input-label for="months" :value="'مدة الاشتراك (بالأشهر)'" />
                        <input
                            id="months"
                            name="months"
                            type="number"
                            min="1"
                            max="60"
                            step="1"
                            value="{{ old('months', 1) }}"
                            required
                            class="mt-1.5 w-40 rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm transition focus:border-emerald focus:ring-emerald/30"
                        >
                    </div>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                        تحديث الاشتراك
                    </button>
                    <p class="w-full text-xs text-ink-soft">يُحتسب التاريخ من الآن. القيمة بين 1 و60 شهراً.</p>
                </form>
            </div>
        </x-card>

        <!-- Team seats (max_users) — ADMIN-only; setMaxUsers via trusted save (§13) -->
        <x-card title="مقاعد الفريق" subtitle="عدد المستخدمين المسموح به للعميل (المالك + الموظفون).">
            <div class="space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-ink/10 bg-paper/50 p-4">
                    <div>
                        <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">المقاعد المستخدمة حالياً</p>
                        <p class="mt-1 text-xl font-bold text-ink tabular-nums">{{ $seatsUsed }} <span class="text-ink-soft">/ {{ $customer->max_users }}</span></p>
                    </div>
                </div>

                <form method="POST" action="{{ route('admin.customers.seats', $customer->id) }}" class="flex flex-wrap items-end gap-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <x-input-label for="max_users" :value="'عدد المقاعد'" />
                        <input
                            id="max_users"
                            name="max_users"
                            type="number"
                            min="1"
                            max="100"
                            step="1"
                            value="{{ old('max_users', $customer->max_users) }}"
                            required
                            class="mt-1.5 w-40 rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm transition focus:border-emerald focus:ring-emerald/30"
                        >
                    </div>
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                        تحديث المقاعد
                    </button>
                    <p class="w-full text-xs text-ink-soft">القيمة بين 1 و100. لا يستطيع العميل تجاوز هذا الحدّ.</p>
                </form>
            </div>
        </x-card>

        <!-- Bot provider / model -->
        @if ($account)
            <x-card title="إعداد بوت العميل" subtitle="اختر مزوّد الذكاء الاصطناعي والنموذج المستخدم.">
                <form method="POST" action="{{ route('admin.customers.bot', $customer->id) }}" class="space-y-5">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="ai_provider" :value="'مزوّد الذكاء الاصطناعي'" />
                        <select
                            id="ai_provider"
                            name="ai_provider"
                            class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm transition focus:border-emerald focus:ring-emerald/30 sm:w-72"
                        >
                            @foreach ($providers as $provider)
                                <option value="{{ $provider }}" @selected(old('ai_provider', $account->ai_provider) === $provider)>
                                    @switch($provider)
                                        @case('gemini') Gemini (مفعّل) @break
                                        @case('openai') OpenAI (يُفعّل لاحقاً عند إضافة المفتاح) @break
                                        @case('deepseek') DeepSeek (يُفعّل لاحقاً عند إضافة المفتاح) @break
                                        @default {{ $provider }}
                                    @endswitch
                                </option>
                            @endforeach
                        </select>
                        <p class="mt-2 text-xs text-ink-soft">Gemini هو المزوّد المفعّل حالياً. الباقي يُخزَّن ويُفعَّل لاحقاً عند إضافة مفتاحه.</p>
                    </div>

                    <div>
                        <x-input-label for="ai_model" :value="'اسم النموذج'" />
                        <input
                            id="ai_model"
                            name="ai_model"
                            type="text"
                            value="{{ old('ai_model', $account->ai_model) }}"
                            required
                            dir="ltr"
                            class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-start font-mono text-sm text-ink shadow-sm transition focus:border-emerald focus:ring-emerald/30 sm:w-72"
                        >
                    </div>

                    <div class="flex justify-end border-t border-ink/10 pt-5">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                            حفظ إعدادات البوت
                        </button>
                    </div>
                </form>
            </x-card>
        @endif

        <!-- Message the customer (email channel, §13). Body is escaped in the email. -->
        <x-card title="مراسلة العميل" subtitle="أرسِل رسالة بريد إلكتروني لمالك حساب هذا العميل.">
            <form method="POST" action="{{ route('admin.customers.messages.store', $customer->id) }}" class="space-y-5">
                @csrf

                <div>
                    <x-input-label for="channel" :value="'القناة'" />
                    <select
                        id="channel"
                        name="channel_display"
                        disabled
                        class="mt-1.5 block w-full rounded-xl border-ink/15 bg-paper/60 text-sm text-ink-soft shadow-sm sm:w-72"
                    >
                        <option value="email" selected>بريد إلكتروني</option>
                        <option value="whatsapp" disabled>واتساب — قريباً</option>
                    </select>
                    <p class="mt-2 text-xs text-ink-soft">القناة المتاحة حالياً هي البريد الإلكتروني فقط. مراسلة واتساب تُضاف لاحقاً.</p>
                </div>

                <div>
                    <x-input-label for="subject" :value="'الموضوع'" />
                    <input
                        id="subject"
                        name="subject"
                        type="text"
                        value="{{ old('subject') }}"
                        maxlength="200"
                        required
                        class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm transition focus:border-emerald focus:ring-emerald/30"
                    >
                </div>

                <div>
                    <x-input-label for="body" :value="'نص الرسالة'" />
                    <textarea
                        id="body"
                        name="body"
                        rows="5"
                        maxlength="5000"
                        required
                        class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm transition focus:border-emerald focus:ring-emerald/30"
                    >{{ old('body') }}</textarea>
                    <p class="mt-2 text-xs text-ink-soft">حتى 5000 حرف. تُرسَل الرسالة إلى بريد المالك المسجّل.</p>
                </div>

                <div class="flex justify-end border-t border-ink/10 pt-5">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
                        إرسال الرسالة
                    </button>
                </div>
            </form>
        </x-card>

        <!-- Sent message history for THIS customer (last 10, newest first) -->
        <x-card title="الرسائل المُرسَلة" subtitle="آخر 10 رسائل أُرسِلت لهذا العميل.">
            @if ($sentMessages->isEmpty())
                <p class="py-4 text-center text-sm text-ink-soft">لم تُرسَل أي رسالة لهذا العميل بعد.</p>
            @else
                <ul class="divide-y divide-ink/10">
                    @foreach ($sentMessages as $message)
                        <li class="flex flex-wrap items-start justify-between gap-3 py-3.5">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="inline-flex items-center rounded-full border border-ink/10 bg-paper/60 px-2 py-0.5 font-mono text-[10px] uppercase tracking-wider text-ink-soft">{{ $message->channel }}</span>
                                    <p class="truncate text-sm font-semibold text-ink">{{ $message->subject }}</p>
                                </div>
                                <p class="mt-1 text-xs text-ink-soft">المُرسِل: {{ $message->sentBy?->name ?? '—' }}</p>
                            </div>
                            <span class="shrink-0 font-mono text-xs text-ink-2">{{ $message->created_at?->format('Y-m-d H:i') ?? '—' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-admin-layout>
