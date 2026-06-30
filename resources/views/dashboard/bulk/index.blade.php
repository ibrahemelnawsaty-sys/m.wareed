<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">الرسائل الجماعية</h1>
            <p class="text-sm text-ink-soft">أرسِل رسالة واحدة لكل جهات الاتصال المؤهّلة، ضمن حدود حماية رقمك.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if (session('status') === 'bulk-campaign-queued')
            <div class="rounded-xl border border-emerald/30 bg-emerald/5 px-4 py-3 text-sm font-medium text-emerald-deep">
                تمت جدولة الحملة. ستُرسَل تدريجياً عبر الطابور مع احترام السقف اليومي والنافذة.
            </div>
        @elseif (session('status') === 'bulk-campaign-stopped')
            <div class="rounded-xl border border-gold/30 bg-gold/10 px-4 py-3 text-sm font-medium text-ink-2">
                تم إيقاف الحملة؛ لن تُرسَل الرسائل المتبقّية.
            </div>
        @elseif (session('status') === 'bulk-contact-resubscribed')
            <div class="rounded-xl border border-emerald/30 bg-emerald/5 px-4 py-3 text-sm font-medium text-emerald-deep">
                تمت إعادة اشتراك جهة الاتصال؛ ستظهر ضمن المؤهّلين في الحملات القادمة.
            </div>
        @endif

        {{-- Meta safety guidance — prominent, this is the high-risk path. --}}
        <div class="rounded-2xl border border-gold/30 bg-gold/10 p-5 shadow-luxe">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-6 w-6 shrink-0 text-gold" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-ink">قواعد Meta لحماية رقمك من الحظر</h2>
                    <ul class="mt-2 space-y-1.5 text-xs leading-relaxed text-ink-2">
                        <li>• <strong>موافقة مسبقة (opt-in):</strong> نُرسِل فقط لمن تفاعل معك (له محادثة) ولم ينسحب.</li>
                        <li>• <strong>سقف يومي 250:</strong> عند بلوغه يُقفَل الإرسال تلقائياً لحماية الرقم.</li>
                        <li>• <strong>نافذة 24 ساعة:</strong> من أُغلقت نافذته يُتخطّى (يحتاج قالباً معتمداً — قريباً).</li>
                        <li>• <strong>الانسحاب (opt-out):</strong> من يرسل «إيقاف/إلغاء الاشتراك/stop» يُستبعَد فوراً.</li>
                        <li>• <strong>تقييم الجودة:</strong> أوقِف الحملات عند تحوّل التقييم للأصفر/الأحمر.</li>
                    </ul>
                    <a href="/docs/META_REQUIREMENTS.md" class="mt-2 inline-block text-xs font-semibold text-emerald-deep underline">اقرأ متطلبات Meta كاملة</a>
                </div>
            </div>
        </div>

        {{-- Live figures + new-campaign form. --}}
        <x-card title="حملة جديدة" subtitle="تُرسَل لكل المؤهّلين الآن. النص حرّ ويُرسَل لمن نافذته مفتوحة فقط.">
            @php
                $disabled = $account === null || $eligibleCount === 0 || $remaining === 0;
            @endphp

            <div class="mb-5 grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl border border-ink/10 bg-paper/40 p-4">
                    <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">المؤهّلون الآن</p>
                    <p class="mt-1 text-2xl font-bold text-ink tabular-nums">{{ $eligibleCount }}</p>
                </div>
                <div class="rounded-xl border border-ink/10 bg-paper/40 p-4">
                    <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">المتبقّي من السقف اليومي</p>
                    <p class="mt-1 text-2xl font-bold tabular-nums {{ $remaining === 0 ? 'text-[#B5462F]' : 'text-ink' }}">{{ $remaining }}</p>
                </div>
            </div>

            @if ($account === null)
                <div class="rounded-xl border border-dashed border-ink/15 bg-paper/50 p-6 text-center">
                    <p class="text-sm font-medium text-ink-2">لا يوجد رقم واتساب مربوط بعد.</p>
                    <p class="mt-1 text-xs text-ink-soft">اربط رقمك من صفحة «ربط واتساب» لبدء الإرسال الجماعي.</p>
                </div>
            @else
                <form method="POST" action="{{ route('bulk.store') }}" class="space-y-5">
                    @csrf

                    <div>
                        <x-input-label for="body" :value="'نص الرسالة'" />
                        <textarea id="body" name="body" rows="5" required
                                  class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm focus:border-emerald focus:ring-emerald"
                                  placeholder="اكتب رسالتك هنا…">{{ old('body') }}</textarea>
                        <x-input-error :messages="$errors->get('body')" class="mt-2" />
                        <p class="mt-1.5 text-xs text-ink-soft">
                            ستُرسَل إلى {{ $eligibleCount }} جهة مؤهّلة، بحد أقصى {{ $remaining }} اليوم. من نافذته مغلقة أو منسحب يُتخطّى.
                        </p>
                    </div>

                    <div class="flex items-center justify-between border-t border-ink/10 pt-5">
                        @if ($eligibleCount > $remaining)
                            <p class="text-xs font-semibold text-[#B5462F]">عدد المؤهّلين ({{ $eligibleCount }}) يتجاوز المتبقّي من السقف ({{ $remaining }}).</p>
                        @else
                            <span></span>
                        @endif
                        <x-primary-button type="submit" @disabled($disabled)
                            @class(['opacity-50 cursor-not-allowed' => $disabled])>
                            إرسال للمؤهّلين
                        </x-primary-button>
                    </div>
                </form>
            @endif
        </x-card>

        {{-- Past + running campaigns. --}}
        <x-card title="الحملات" subtitle="حالة كل حملة وعدّاداتها.">
            @if ($campaigns->isEmpty())
                <p class="py-6 text-center text-sm text-ink-soft">لا توجد حملات بعد.</p>
            @else
                <ul class="divide-y divide-ink/10">
                    @foreach ($campaigns as $campaign)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3.5">
                            <div class="min-w-0">
                                <a href="{{ route('bulk.show', $campaign) }}" class="block truncate text-sm font-semibold text-ink hover:text-emerald-deep">
                                    {{ \Illuminate\Support\Str::limit($campaign->body, 60) }}
                                </a>
                                <p class="mt-0.5 text-xs text-ink-soft tabular-nums">
                                    {{ $campaign->created_at?->format('Y-m-d H:i') }} ·
                                    أُرسِل {{ $campaign->sent_count }} ·
                                    تُخطّي {{ $campaign->skipped_count }} ·
                                    فشل {{ $campaign->failed_count }}
                                    / {{ $campaign->recipients_total }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                @php
                                    $badge = match ($campaign->status) {
                                        'completed' => ['تمّت', 'border-emerald/30 bg-emerald/10 text-emerald-deep'],
                                        'sending' => ['جارية', 'border-gold/30 bg-gold/10 text-ink-2'],
                                        'stopped' => ['موقوفة', 'border-[#B5462F]/30 bg-[#B5462F]/5 text-[#B5462F]'],
                                        default => ['في الطابور', 'border-ink/10 bg-paper/60 text-ink-soft'],
                                    };
                                @endphp
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>

                                @if (in_array($campaign->status, ['queued', 'sending'], true))
                                    <form method="POST" action="{{ route('bulk.stop', $campaign) }}"
                                          onsubmit="return confirm('إيقاف هذه الحملة؟ لن تُرسَل الرسائل المتبقّية.');">
                                        @csrf
                                        <button type="submit" class="rounded-lg border border-[#B5462F]/20 px-2.5 py-1.5 text-xs font-semibold text-[#B5462F] transition hover:bg-[#B5462F]/5">إيقاف</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4">
                    {{ $campaigns->links() }}
                </div>
            @endif
        </x-card>

        {{-- Opted-out contacts — reversible (§9): a keyword like "bus stop" can
             opt someone out by mistake, so the owner can bring them back. --}}
        <x-card title="المنسحبون" subtitle="جهات اختارت الانسحاب وتُستبعَد من الحملات. أعِد اشتراك من انسحب بالخطأ.">
            @if ($optedOut->isEmpty())
                <p class="py-6 text-center text-sm text-ink-soft">لا يوجد منسحبون.</p>
            @else
                <ul class="divide-y divide-ink/10">
                    @foreach ($optedOut as $contact)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3.5">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-ink">{{ $contact->contact_name ?? $contact->wa_contact_id }}</p>
                                <p class="mt-0.5 text-xs text-ink-soft tabular-nums">انسحب في {{ $contact->opted_out_at?->format('Y-m-d H:i') }}</p>
                            </div>
                            <form method="POST" action="{{ route('bulk.resubscribe', $contact) }}"
                                  onsubmit="return confirm('إعادة اشتراك هذه الجهة في الحملات؟');">
                                @csrf
                                <button type="submit" class="rounded-lg border border-emerald/30 px-2.5 py-1.5 text-xs font-semibold text-emerald-deep transition hover:bg-emerald/5">إعادة الاشتراك</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-app-layout>
