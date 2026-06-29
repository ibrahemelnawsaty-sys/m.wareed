<x-admin-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">التحليلات</h1>
            <p class="text-sm text-ink-soft">مجاميع المنصّة عبر كل العملاء.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Today -->
        <div>
            <p class="mb-2 font-mono text-[11px] uppercase tracking-wider text-ink-soft">اليوم</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                    <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">الرسائل</p>
                    <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($messagesToday) }}</p>
                </div>
                <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                    <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">التوكنز</p>
                    <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($tokensToday) }}</p>
                </div>
                <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                    <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">التكلفة</p>
                    <p class="mt-2 text-2xl font-bold text-emerald"><x-cost :micros="$costTodayMicros" /></p>
                </div>
            </div>
        </div>

        <!-- 30 days -->
        <div>
            <p class="mb-2 font-mono text-[11px] uppercase tracking-wider text-ink-soft">آخر 30 يوماً</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                    <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">الرسائل</p>
                    <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($messages30) }}</p>
                </div>
                <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                    <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">التوكنز</p>
                    <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($tokens30) }}</p>
                </div>
                <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                    <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">التكلفة</p>
                    <p class="mt-2 text-2xl font-bold text-emerald"><x-cost :micros="$cost30Micros" /></p>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Status distribution (CSS bars, no JS, §14) -->
            <x-card title="توزّع العملاء حسب الحالة">
                @php
                    $rows = [
                        ['key' => 'active', 'label' => 'نشط', 'value' => $statusCounts['active'], 'bar' => 'bg-emerald'],
                        ['key' => 'pending', 'label' => 'قيد المراجعة', 'value' => $statusCounts['pending'], 'bar' => 'bg-gold'],
                        ['key' => 'suspended', 'label' => 'موقوف', 'value' => $statusCounts['suspended'], 'bar' => 'bg-[#B5462F]'],
                    ];
                @endphp
                @if ($totalCustomers === 0)
                    <p class="py-6 text-center text-sm text-ink-soft">لا يوجد عملاء بعد.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($rows as $row)
                            @php $pct = $totalCustomers > 0 ? (int) round($row['value'] / $totalCustomers * 100) : 0; @endphp
                            <div>
                                <div class="mb-1.5 flex items-center justify-between text-sm">
                                    <span class="font-medium text-ink-2">{{ $row['label'] }}</span>
                                    <span class="font-mono text-xs text-ink-soft tabular-nums">{{ number_format($row['value']) }} · {{ $pct }}%</span>
                                </div>
                                <div class="h-2.5 w-full overflow-hidden rounded-full bg-paper">
                                    <div class="h-full rounded-full {{ $row['bar'] }} transition-all" style="width: {{ $pct }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>

            <!-- Top 10 customers by message volume -->
            <x-card title="أعلى 10 عملاء استهلاكاً" subtitle="حسب إجمالي الرسائل المُسجّلة.">
                @if (empty($topCustomers))
                    <p class="py-6 text-center text-sm text-ink-soft">لا توجد بيانات استهلاك بعد.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($topCustomers as $row)
                            @php $pct = $maxTopMessages > 0 ? (int) round($row['messages'] / $maxTopMessages * 100) : 0; @endphp
                            <div>
                                <div class="mb-1.5 flex items-center justify-between gap-3 text-sm">
                                    <span class="truncate font-medium text-ink-2">{{ $row['name'] }}</span>
                                    <span class="shrink-0 font-mono text-xs text-ink-soft tabular-nums">
                                        {{ number_format($row['messages']) }} رسالة · <x-cost :micros="$row['costMicros']" class="!font-mono text-emerald" />
                                    </span>
                                </div>
                                <div class="h-2.5 w-full overflow-hidden rounded-full bg-paper">
                                    <div class="h-full rounded-full bg-emerald/80 transition-all" style="width: {{ max($pct, $row['messages'] > 0 ? 4 : 0) }}%;"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-card>
        </div>
    </div>
</x-admin-layout>
