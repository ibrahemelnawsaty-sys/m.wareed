{{--
    Platform AI keys — super-admin only (§13). These are platform secrets:
    the page renders PRESENCE ONLY (stored ✓ / not set). No real key value is
    ever passed to this view nor printed here. Every field is an EMPTY password
    input; submitting it blank keeps the stored key (non-destructive, §3).
--}}
<x-admin-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">الإعدادات</h1>
            <p class="text-sm text-ink-soft">مفاتيح مزوّدي الذكاء الاصطناعي على مستوى المنصّة.</p>
        </div>
    </x-slot>

    @php
        $providers = [
            [
                'field' => 'gemini_api_key',
                'name' => 'Gemini',
                'has' => $hasGemini,
                'desc' => 'المزوّد الافتراضي المفعّل لكل العملاء ما لم يُختَر غيره.',
            ],
            [
                'field' => 'openai_api_key',
                'name' => 'OpenAI',
                'has' => $hasOpenai,
                'desc' => 'يُفعَّل للعميل بعد إضافة مفتاحه هنا واختياره من صفحة العميل.',
            ],
            [
                'field' => 'deepseek_api_key',
                'name' => 'DeepSeek',
                'has' => $hasDeepseek,
                'desc' => 'يُفعَّل للعميل بعد إضافة مفتاحه هنا واختياره من صفحة العميل.',
            ],
        ];
    @endphp

    <div class="space-y-6">
        <!-- Validation errors (never echoes a submitted key, §13) -->
        @if ($errors->any())
            <div class="rounded-xl border border-[#B5462F]/30 bg-[#B5462F]/5 px-4 py-3 text-sm font-medium text-[#B5462F]">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Priority + activation note -->
        <div class="rounded-2xl border border-gold/30 bg-gold/5 px-5 py-4 text-sm text-ink-2">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-gold" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
                <div class="space-y-1">
                    <p class="font-semibold text-ink">ترتيب أولوية المفاتيح</p>
                    <p>مفتاح العميل الخاص ← مفتاح المنصّة هنا ← متغيّر البيئة.</p>
                    <p class="text-ink-soft">Gemini مفعّل افتراضياً؛ فعّل OpenAI أو DeepSeek بإضافة مفتاحه هنا ثم اختياره للعميل من صفحته.</p>
                </div>
            </div>
        </div>

        <x-card title="مفاتيح مزوّدي الذكاء الاصطناعي" subtitle="اترك الحقل فارغاً للإبقاء على المفتاح الحالي. القيم مشفّرة ولا تُعرَض أبداً.">
            <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                @foreach ($providers as $provider)
                    <div class="rounded-xl border border-ink/10 bg-paper/40 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2">
                                <x-input-label :for="$provider['field']" :value="'مفتاح '.$provider['name']" />
                                @if ($provider['has'])
                                    <span class="inline-flex items-center gap-1 rounded-full border border-signal/30 bg-signal/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-deep">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                        مُخزّن
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full border border-ink/15 bg-white px-2.5 py-0.5 text-xs font-semibold text-ink-soft">
                                        غير مُدخل
                                    </span>
                                @endif
                            </div>
                        </div>

                        <input
                            id="{{ $provider['field'] }}"
                            name="{{ $provider['field'] }}"
                            type="password"
                            value=""
                            autocomplete="new-password"
                            dir="ltr"
                            placeholder="اتركه فارغاً للإبقاء على المفتاح الحالي"
                            class="mt-2 block w-full rounded-xl border-ink/15 bg-white text-start font-mono text-sm text-ink shadow-sm transition focus:border-emerald focus:ring-emerald/30"
                        >
                        <p class="mt-2 text-xs text-ink-soft">{{ $provider['desc'] }}</p>
                    </div>
                @endforeach

                <div class="flex justify-end border-t border-ink/10 pt-5">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>
                        حفظ المفاتيح
                    </button>
                </div>
            </form>
        </x-card>
    </div>
</x-admin-layout>
