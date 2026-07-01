<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">القوالب المعتمدة</h1>
            <p class="text-sm text-ink-soft">قوالب واتساب المعتمدة من ميتا — تتيح مراسلة عملائك حتى خارج نافذة 24 ساعة.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @php
            $syncStatus = session('status');
            $syncedCount = is_string($syncStatus) && str_starts_with($syncStatus, 'synced:')
                ? (int) substr($syncStatus, strlen('synced:'))
                : null;
        @endphp

        @if ($syncedCount !== null)
            <div class="rounded-xl border border-emerald/30 bg-emerald/5 px-4 py-3 text-sm font-medium text-emerald-deep">
                تمت المزامنة من ميتا بنجاح: {{ $syncedCount }} قالب/قوالب.
            </div>
        @elseif ($syncStatus === 'template-added')
            <div class="rounded-xl border border-emerald/30 bg-emerald/5 px-4 py-3 text-sm font-medium text-emerald-deep">
                تمت إضافة القالب. لن يكون قابلاً للإرسال حتى تؤكّد مزامنة ميتا أنه «معتمد».
            </div>
        @endif

        <x-input-error :messages="$errors->get('sync')" class="rounded-xl border border-[#B5462F]/30 bg-[#B5462F]/5 px-4 py-3" />

        {{-- Guidance — templates live in Meta; this screen mirrors them. --}}
        <div class="rounded-2xl border border-gold/30 bg-gold/10 p-5 shadow-luxe">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-6 w-6 shrink-0 text-gold" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" /></svg>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-ink">كيف تعمل القوالب</h2>
                    <ul class="mt-2 space-y-1.5 text-xs leading-relaxed text-ink-2">
                        <li>• القوالب تُنشأ وتُراجَع وتُعتمَد في <strong>Meta Business Manager</strong>، لا من هنا.</li>
                        <li>• اضغط «مزامنة من ميتا» لجلب حالة كل قالب (معتمد/قيد المراجعة/مرفوض).</li>
                        <li>• <strong>القوالب المعتمدة فقط</strong> تظهر في نموذج الرسائل الجماعية وتُرسَل خارج نافذة 24 ساعة.</li>
                        <li>• تستخدم القوالب التسويقية موافقة العميل (opt-in) ويُطبَّق السقف اليومي 250 كما هو.</li>
                    </ul>
                    <a href="/docs/META_REQUIREMENTS.md" class="mt-2 inline-block text-xs font-semibold text-emerald-deep underline">اقرأ متطلبات Meta كاملة</a>
                </div>
            </div>
        </div>

        {{-- Sync action. --}}
        <x-card title="مزامنة القوالب" subtitle="اجلب حالة قوالبك المحدّثة من ميتا.">
            @if ($account === null)
                <div class="rounded-xl border border-dashed border-ink/15 bg-paper/50 p-6 text-center">
                    <p class="text-sm font-medium text-ink-2">لا يوجد رقم واتساب مربوط بعد.</p>
                    <p class="mt-1 text-xs text-ink-soft">اربط رقمك من صفحة «ربط واتساب» أولاً.</p>
                </div>
            @else
                <form method="POST" action="{{ route('templates.sync') }}" class="flex items-center justify-between gap-3">
                    @csrf
                    <p class="text-xs text-ink-soft">
                        @if ($syncReady)
                            ستُحدَّث الحالات والفئات وعدد المتغيّرات من ميتا.
                        @else
                            أكمِل بيانات الربط (معرّف WABA والتوكن) لتفعيل المزامنة.
                        @endif
                    </p>
                    @php $syncDisabled = ! $syncReady; @endphp
                    <x-primary-button type="submit" :disabled="$syncDisabled" class="{{ $syncDisabled ? 'opacity-50 cursor-not-allowed' : '' }}">
                        مزامنة من ميتا
                    </x-primary-button>
                </form>
            @endif
        </x-card>

        {{-- Templates list. --}}
        <x-card title="القوالب" subtitle="كل قوالبك وحالتها الحالية.">
            @if ($templates->isEmpty())
                <p class="py-6 text-center text-sm text-ink-soft">لا توجد قوالب بعد. زامِن من ميتا أو أضِف قالباً يدوياً.</p>
            @else
                <ul class="divide-y divide-ink/10">
                    @foreach ($templates as $template)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-ink">
                                    {{ $template->name }}
                                    <span class="font-mono text-xs text-ink-soft">· {{ $template->language }}</span>
                                </p>
                                @if ($template->body_text)
                                    <p class="mt-0.5 truncate text-xs text-ink-soft">{{ \Illuminate\Support\Str::limit($template->body_text, 80) }}</p>
                                @endif
                                <p class="mt-0.5 text-xs text-ink-soft tabular-nums">
                                    الفئة: {{ $template->category }} ·
                                    المتغيّرات: {{ $template->variable_count }}
                                    @if ($template->last_synced_at)
                                        · آخر مزامنة {{ $template->last_synced_at->format('Y-m-d H:i') }}
                                    @endif
                                </p>
                            </div>
                            @php
                                $badge = match ($template->status) {
                                    'approved' => ['معتمد', 'border-emerald/30 bg-emerald/10 text-emerald-deep'],
                                    'pending' => ['قيد المراجعة', 'border-gold/30 bg-gold/10 text-ink-2'],
                                    'rejected' => ['مرفوض', 'border-[#B5462F]/30 bg-[#B5462F]/5 text-[#B5462F]'],
                                    'paused' => ['موقوف', 'border-[#B5462F]/30 bg-[#B5462F]/5 text-[#B5462F]'],
                                    'disabled' => ['معطّل', 'border-[#B5462F]/30 bg-[#B5462F]/5 text-[#B5462F]'],
                                    default => ['غير معروف', 'border-ink/10 bg-paper/60 text-ink-soft'],
                                };
                            @endphp
                            <span class="inline-flex shrink-0 items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4">
                    {{ $templates->links() }}
                </div>
            @endif
        </x-card>

        {{-- Manual add — fallback when sync is unavailable. --}}
        <x-card title="إضافة قالب يدوياً" subtitle="للقوالب المُنشأة في ميتا حين تتعذّر المزامنة. يبقى غير قابل للإرسال حتى تؤكّد مزامنة ميتا اعتماده.">
            @if ($account === null)
                <p class="py-2 text-sm text-ink-soft">اربط رقمك أولاً لإضافة قالب.</p>
            @else
                <form method="POST" action="{{ route('templates.store') }}" class="space-y-5">
                    @csrf

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="name" :value="'اسم القالب (مثل order_update)'" />
                            <input id="name" name="name" type="text" required maxlength="512"
                                value="{{ old('name') }}"
                                class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm focus:border-emerald focus:ring-emerald/30"
                                placeholder="order_update">
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="language" :value="'رمز اللغة (مثل ar أو en_US)'" />
                            <input id="language" name="language" type="text" required maxlength="15"
                                value="{{ old('language') }}"
                                class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm focus:border-emerald focus:ring-emerald/30"
                                placeholder="ar">
                            <x-input-error :messages="$errors->get('language')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="category" :value="'الفئة'" />
                        <select id="category" name="category" required
                            class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm focus:border-emerald focus:ring-emerald/30">
                            <option value="utility" @selected(old('category') === 'utility')>Utility (إشعارات/معاملات)</option>
                            <option value="marketing" @selected(old('category') === 'marketing')>Marketing (تسويق)</option>
                            <option value="authentication" @selected(old('category') === 'authentication')>Authentication (تحقق)</option>
                        </select>
                        <x-input-error :messages="$errors->get('category')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="body_text" :value="'نص القالب (اختياري · استخدم @{{1}} للمتغيّرات)'" />
                        <textarea id="body_text" name="body_text" rows="4" maxlength="4000"
                            class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm leading-relaxed text-ink shadow-sm focus:border-emerald focus:ring-emerald/30"
                            placeholder="مرحباً @{{1}}، تم تحديث طلبك رقم @{{2}}.">{{ old('body_text') }}</textarea>
                        <x-input-error :messages="$errors->get('body_text')" class="mt-2" />
                        <p class="mt-1.5 text-xs text-ink-soft">يُحتسب عدد المتغيّرات تلقائياً من عدد @{{n}} في النص.</p>
                    </div>

                    <div class="flex justify-end border-t border-ink/10 pt-5">
                        <x-primary-button type="submit">إضافة القالب</x-primary-button>
                    </div>
                </form>
            @endif
        </x-card>
    </div>
</x-app-layout>
