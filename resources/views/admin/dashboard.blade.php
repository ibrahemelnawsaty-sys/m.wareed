<x-admin-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">لوحة الأدمن</h1>
            <p class="text-sm text-ink-soft">نظرة شاملة على كل عملاء المنصّة.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Cross-tenant stat cards (every figure via withoutGlobalScopes, §1) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">إجمالي العملاء</p>
                <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($totalCustomers) }}</p>
            </div>
            <div class="rounded-2xl border border-gold/30 bg-gold/5 p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-gold">قيد المراجعة</p>
                <p class="mt-2 text-2xl font-bold text-gold tabular-nums">{{ number_format($pendingCount) }}</p>
            </div>
            <div class="rounded-2xl border border-signal/30 bg-signal/5 p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-emerald-deep">النشطون</p>
                <p class="mt-2 text-2xl font-bold text-emerald tabular-nums">{{ number_format($activeCount) }}</p>
            </div>
            <div class="rounded-2xl border border-[#B5462F]/30 bg-[#B5462F]/5 p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-[#B5462F]">الموقوفون</p>
                <p class="mt-2 text-2xl font-bold text-[#B5462F] tabular-nums">{{ number_format($suspendedCount) }}</p>
            </div>
        </div>

        <!-- Platform usage totals -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">إجمالي الرسائل (كل العملاء)</p>
                <p class="mt-2 text-2xl font-bold text-ink tabular-nums">{{ number_format($totalMessages) }}</p>
            </div>
            <div class="rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">إجمالي التكلفة (كل العملاء)</p>
                <p class="mt-2 text-2xl font-bold text-emerald"><x-cost :micros="$totalCostMicros" /></p>
            </div>
        </div>

        <!-- Recent signups with a one-click approve -->
        <x-card title="أحدث التسجيلات" subtitle="آخر العملاء الذين انضموا للمنصّة.">
            @if ($recentTenants->isEmpty())
                <p class="py-8 text-center text-sm text-ink-soft">لا يوجد عملاء بعد.</p>
            @else
                <div class="space-y-3">
                    @foreach ($recentTenants as $tenant)
                        @php $owner = $tenant->users->first(); @endphp
                        <div class="flex flex-col gap-3 rounded-xl border border-ink/10 bg-paper/50 p-4 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <a href="{{ route('admin.customers.show', $tenant->id) }}" class="truncate font-bold text-ink hover:text-emerald">{{ $tenant->name }}</a>
                                    <x-admin.status-badge :status="$tenant->status" />
                                </div>
                                <p class="mt-0.5 truncate font-mono text-xs text-ink-soft" dir="ltr">{{ $owner?->email ?? '—' }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                @if ($tenant->status === 'pending')
                                    <form method="POST" action="{{ route('admin.customers.approve', $tenant->id) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald px-3.5 py-2 text-xs font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                            موافقة سريعة
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('admin.customers.show', $tenant->id) }}" class="inline-flex items-center gap-1 rounded-lg border border-ink/10 px-3 py-2 text-xs font-semibold text-emerald transition hover:bg-emerald/5">
                                    التفاصيل
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>
    </div>
</x-admin-layout>
