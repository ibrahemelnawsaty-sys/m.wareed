<x-admin-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">العملاء</h1>
            <p class="text-sm text-ink-soft">كل المستأجرين على المنصّة وحالة اشتراكهم واستهلاكهم.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Search -->
        <form method="GET" action="{{ route('admin.customers.index') }}" class="flex gap-2">
            <div class="relative flex-1">
                <span class="pointer-events-none absolute inset-y-0 start-0 grid w-11 place-items-center text-ink-soft">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
                </span>
                <input
                    type="search"
                    name="search"
                    value="{{ $search }}"
                    placeholder="ابحث بالاسم أو البريد الإلكتروني…"
                    class="block w-full rounded-xl border-ink/15 bg-white ps-11 text-sm text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-emerald focus:ring-emerald/30"
                >
            </div>
            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                بحث
            </button>
            @if ($search !== '')
                <a href="{{ route('admin.customers.index') }}" class="inline-flex items-center gap-2 rounded-xl border border-ink/15 bg-white px-5 py-2.5 text-sm font-semibold text-ink-2 shadow-sm transition hover:bg-paper">
                    إلغاء
                </a>
            @endif
        </form>

        @if ($customers->isEmpty())
            <div class="rounded-2xl border border-dashed border-ink/15 bg-white p-12 text-center shadow-sm">
                <h3 class="text-base font-bold text-ink">لا يوجد عملاء مطابقون</h3>
                <p class="mx-auto mt-1 max-w-sm text-sm text-ink-soft">
                    {{ $search !== '' ? 'لا توجد نتائج لبحثك. جرّب كلمة أخرى.' : 'لم ينضم أي عميل للمنصّة بعد.' }}
                </p>
            </div>
        @else
            <x-card class="overflow-hidden !p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-ink/10 text-sm">
                        <thead>
                            <tr class="text-end font-mono text-[11px] uppercase tracking-wider text-ink-soft">
                                <th class="px-6 py-3 font-medium">الاسم</th>
                                <th class="px-6 py-3 font-medium">المالك</th>
                                <th class="px-6 py-3 font-medium">الحالة</th>
                                <th class="px-6 py-3 font-medium">نهاية الاشتراك</th>
                                <th class="px-6 py-3 font-medium">الرسائل</th>
                                <th class="px-6 py-3 font-medium">التكلفة</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/5">
                            @foreach ($customers as $customer)
                                @php
                                    $owner = $customer->users->first();
                                    $usage = $usageByTenant[$customer->id] ?? null;
                                    $messages = (int) ($usage->messages ?? 0);
                                    $costMicros = (int) ($usage->cost_micros ?? 0);
                                @endphp
                                <tr class="transition hover:bg-paper/60">
                                    <td class="px-6 py-4">
                                        <a href="{{ route('admin.customers.show', $customer->id) }}" class="font-semibold text-ink hover:text-emerald">{{ $customer->name }}</a>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-mono text-xs text-ink-2" dir="ltr">{{ $owner?->email ?? '—' }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <x-admin.status-badge :status="$customer->status" />
                                    </td>
                                    <td class="px-6 py-4 text-ink-2">
                                        @if ($customer->subscription_ends_at)
                                            <span @class([
                                                'font-mono text-xs',
                                                'text-[#B5462F]' => $customer->subscription_ends_at->isPast(),
                                            ]) title="{{ $customer->subscription_ends_at->format('Y-m-d') }}">
                                                {{ $customer->subscription_ends_at->format('Y-m-d') }}
                                            </span>
                                        @else
                                            <span class="text-ink-soft">—</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-mono font-semibold text-ink tabular-nums">{{ number_format($messages) }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <x-cost :micros="$costMicros" class="text-emerald" />
                                    </td>
                                    <td class="px-6 py-4 text-end">
                                        <a href="{{ route('admin.customers.show', $customer->id) }}" class="inline-flex items-center gap-1 rounded-lg border border-ink/10 px-3 py-1.5 text-xs font-semibold text-emerald transition hover:bg-emerald/5">
                                            عرض
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <div>{{ $customers->links() }}</div>
        @endif
    </div>
</x-admin-layout>
