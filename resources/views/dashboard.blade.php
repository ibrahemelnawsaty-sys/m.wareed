<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">لوحة التحكم</h1>
            <p class="text-sm text-ink-soft">أهلاً {{ Auth::user()->name }}، هذه نظرة سريعة على بوتك.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Status banner -->
        <div @class([
            'flex flex-col gap-4 rounded-2xl border p-6 shadow-luxe sm:flex-row sm:items-center sm:justify-between',
            'border-signal/30 bg-signal/10' => $isConnected,
            'border-gold/30 bg-gold/10' => ! $isConnected,
        ])>
            <div class="flex items-start gap-4">
                <span @class([
                    'grid h-12 w-12 shrink-0 place-items-center rounded-xl',
                    'bg-signal/20 text-emerald-deep' => $isConnected,
                    'bg-gold/20 text-gold' => ! $isConnected,
                ])>
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5l1.2-3.6A8.25 8.25 0 1 1 7.5 21Z" />
                    </svg>
                </span>
                <div>
                    <h2 class="text-base font-bold text-ink">
                        {{ $isConnected ? 'البوت مرتبط وجاهز' : 'أكمل ربط رقم واتساب' }}
                    </h2>
                    <p class="mt-1 text-sm text-ink-soft">
                        {{ $isConnected
                            ? 'رقم واتساب مرتبط ويردّ بوتك آلياً على عملائك.'
                            : 'لم تكتمل بيانات الربط بعد. أضف معرّف الرقم والتوكن لتفعيل البوت.' }}
                    </p>
                </div>
            </div>
            @unless ($isConnected)
                <a href="{{ route('whatsapp.edit') }}" class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                    إكمال الربط
                </a>
            @endunless
        </div>

        <!-- Live usage stats (tenant-scoped, §1) -->
        <div class="grid gap-4 sm:grid-cols-3">
            <a href="{{ route('conversations.index') }}" class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe transition hover:border-emerald/30">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">رسائل اليوم</p>
                <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($messagesToday) }}</p>
            </a>
            <a href="{{ route('conversations.index') }}" class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe transition hover:border-emerald/30">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">محادثات نشطة</p>
                <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($activeConversations) }}</p>
            </a>
            <a href="{{ route('analytics.index') }}" class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe transition hover:border-emerald/30">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">تكلفة الشهر</p>
                <p class="mt-2 text-2xl font-bold text-emerald"><x-cost :micros="$monthCostMicros" /></p>
            </a>
        </div>

        <!-- Account info -->
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">حالة الحساب</p>
                <p class="mt-2 text-xl font-bold text-ink">
                    {{ $account?->status === 'active' ? 'نشط' : 'قيد الإعداد' }}
                </p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">النموذج الذكي</p>
                <p class="mt-2 truncate font-mono text-sm font-semibold text-emerald">{{ $account?->ai_model ?? 'gemini-2.5-flash-lite' }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">مستندات المعرفة</p>
                <p class="mt-2 text-xl font-bold text-ink">{{ $knowledgeCount }}</p>
            </div>
        </div>

        <!-- Shortcuts -->
        <div class="grid gap-4 sm:grid-cols-3">
            <a href="{{ route('whatsapp.edit') }}" class="group rounded-2xl border border-ink/10 bg-white p-6 shadow-luxe transition hover:-translate-y-0.5 hover:shadow-luxe-lg">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-emerald/10 text-emerald">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5l1.2-3.6A8.25 8.25 0 1 1 7.5 21Z" /></svg>
                </span>
                <h3 class="mt-4 font-bold text-ink">ربط واتساب</h3>
                <p class="mt-1 text-sm text-ink-soft">أدخل بيانات Cloud API واربط رقمك.</p>
            </a>
            <a href="{{ route('bot.edit') }}" class="group rounded-2xl border border-ink/10 bg-white p-6 shadow-luxe transition hover:-translate-y-0.5 hover:shadow-luxe-lg">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-gold/10 text-gold">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3" /></svg>
                </span>
                <h3 class="mt-4 font-bold text-ink">إعداد البوت</h3>
                <p class="mt-1 text-sm text-ink-soft">اضبط شخصية البوت ودرجة إبداعه.</p>
            </a>
            <a href="{{ route('knowledge.index') }}" class="group rounded-2xl border border-ink/10 bg-white p-6 shadow-luxe transition hover:-translate-y-0.5 hover:shadow-luxe-lg">
                <span class="grid h-11 w-11 place-items-center rounded-xl bg-night/10 text-night">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                </span>
                <h3 class="mt-4 font-bold text-ink">قاعدة المعرفة</h3>
                <p class="mt-1 text-sm text-ink-soft">أضف معلومات يعتمد عليها البوت في الرد.</p>
            </a>
        </div>
    </div>
</x-app-layout>
