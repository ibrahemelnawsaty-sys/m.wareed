<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('bulk.index') }}" class="grid h-9 w-9 place-items-center rounded-lg border border-ink/10 text-ink-soft transition hover:bg-paper" aria-label="رجوع">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5 15.75 12l-7.5 7.5" /></svg>
            </a>
            <div>
                <h1 class="text-lg font-bold text-ink">تفاصيل الحملة</h1>
                <p class="text-sm text-ink-soft tabular-nums">
                    أُرسِل {{ $campaign->sent_count }} · تُخطّي {{ $campaign->skipped_count }} · فشل {{ $campaign->failed_count }} / {{ $campaign->recipients_total }}
                </p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-card title="نص الرسالة">
            <p class="whitespace-pre-wrap text-sm leading-relaxed text-ink">{{ $campaign->body }}</p>
        </x-card>

        <x-card title="المستلمون" subtitle="حالة كل جهة اتصال وسبب التخطّي إن وُجد.">
            @if ($recipients->isEmpty())
                <p class="py-6 text-center text-sm text-ink-soft">لا يوجد مستلمون.</p>
            @else
                <ul class="divide-y divide-ink/10">
                    @foreach ($recipients as $recipient)
                        @php
                            $status = match ($recipient->status) {
                                'sent' => ['أُرسِلت', 'border-emerald/30 bg-emerald/10 text-emerald-deep'],
                                'skipped_window' => ['خارج النافذة (يحتاج قالباً)', 'border-gold/30 bg-gold/10 text-ink-2'],
                                'skipped_optout' => ['منسحب', 'border-ink/15 bg-paper/60 text-ink-soft'],
                                'skipped_cap' => ['بلغ السقف اليومي', 'border-gold/30 bg-gold/10 text-ink-2'],
                                'failed' => ['فشل', 'border-[#B5462F]/30 bg-[#B5462F]/5 text-[#B5462F]'],
                                default => ['قيد الانتظار', 'border-ink/10 bg-paper/60 text-ink-soft'],
                            };
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-ink">
                                    {{ $recipient->conversation?->contact_name ?: 'جهة اتصال' }}
                                </p>
                                <p class="truncate font-mono text-xs text-ink-soft" dir="ltr">{{ $recipient->wa_contact_id }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                @if ($recipient->failed_reason)
                                    <span class="font-mono text-[11px] text-[#B5462F]">{{ $recipient->failed_reason }}</span>
                                @endif
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $status[1] }}">{{ $status[0] }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="mt-4">
                    {{ $recipients->links() }}
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
