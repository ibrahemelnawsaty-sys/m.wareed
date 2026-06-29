<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">التحليلات</h1>
            <p class="text-sm text-ink-soft">نظرة على استهلاك بوتك خلال آخر 30 يوماً.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Stat cards -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">رسائل اليوم</p>
                <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($messagesToday) }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">رسائل 30 يوماً</p>
                <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($messages30) }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">إجمالي التوكنز 30 يوماً</p>
                <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($tokens30) }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">التكلفة 30 يوماً</p>
                <p class="mt-2 text-2xl font-bold text-emerald"><x-cost :micros="$cost30Micros" /></p>
            </div>
        </div>

        <!-- Lightweight CSS bar chart (no JS library, §14) -->
        <x-card title="رسائل آخر 14 يوماً" subtitle="عدد الرسائل المُسجّلة يومياً.">
            @if ($maxMessages === 0)
                <p class="py-8 text-center text-sm text-ink-soft">لا توجد بيانات استهلاك في هذه الفترة بعد.</p>
            @else
                <div class="flex items-end justify-between gap-1.5 sm:gap-2" style="height: 180px;">
                    @foreach ($series as $point)
                        @php
                            // Display-only percentage height; data stays integer.
                            $pct = $maxMessages > 0 ? (int) round($point['messages'] / $maxMessages * 100) : 0;
                            // Keep a sliver visible for non-zero days.
                            $heightPct = $point['messages'] > 0 ? max($pct, 4) : 0;
                        @endphp
                        <div class="group flex flex-1 flex-col items-center justify-end gap-2" style="height: 100%;">
                            <div class="relative flex w-full flex-1 items-end">
                                <div
                                    class="w-full rounded-t-md bg-emerald/80 transition-all group-hover:bg-emerald"
                                    style="height: {{ $heightPct }}%;"
                                    title="{{ $point['date']->format('Y-m-d') }}: {{ $point['messages'] }} رسالة"
                                ></div>
                            </div>
                            <span class="font-mono text-[10px] text-ink-soft">{{ $point['date']->format('d/m') }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
