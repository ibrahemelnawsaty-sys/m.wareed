<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">المحادثات</h1>
            <p class="text-sm text-ink-soft">سجلّ محادثات عملائك مع بوتك (للقراءة فقط).</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($conversations->isEmpty())
            <div class="rounded-2xl border border-dashed border-ink/15 bg-white p-12 text-center shadow-sm">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald/10 text-emerald">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" /></svg>
                </span>
                <h3 class="mt-4 text-base font-bold text-ink">لا توجد محادثات بعد</h3>
                <p class="mx-auto mt-1 max-w-sm text-sm text-ink-soft">ستظهر هنا محادثات عملائك مع بوتك فور بدء التفاعل عبر واتساب.</p>
            </div>
        @else
            <x-card class="overflow-hidden !p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-ink/10 text-sm">
                        <thead>
                            <tr class="text-end text-[11px] font-mono uppercase tracking-wider text-ink-soft">
                                <th class="px-6 py-3 font-medium">رقم العميل</th>
                                <th class="px-6 py-3 font-medium">الحالة</th>
                                <th class="px-6 py-3 font-medium">النافذة</th>
                                <th class="px-6 py-3 font-medium">الرسائل</th>
                                <th class="px-6 py-3 font-medium">آخر نشاط</th>
                                <th class="px-6 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink/5">
                            @foreach ($conversations as $conversation)
                                @php $windowOpen = $conversation->isWindowOpen(); @endphp
                                <tr class="transition hover:bg-paper/60">
                                    <td class="px-6 py-4">
                                        <span class="font-mono font-semibold text-ink" dir="ltr">{{ $conversation->wa_contact_id }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $status = $conversation->status;
                                            $statusLabel = match ($status) {
                                                'open' => 'مفتوحة',
                                                'closed' => 'مغلقة',
                                                default => $status,
                                            };
                                        @endphp
                                        <span @class([
                                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
                                            'bg-signal/15 text-emerald-deep' => $status === 'open',
                                            'bg-ink/5 text-ink-soft' => $status !== 'open',
                                        ])>
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span @class([
                                            'inline-flex items-center gap-1.5 text-xs font-medium',
                                            'text-emerald' => $windowOpen,
                                            'text-ink-soft' => ! $windowOpen,
                                        ])>
                                            <span @class([
                                                'h-1.5 w-1.5 rounded-full',
                                                'bg-emerald' => $windowOpen,
                                                'bg-ink/30' => ! $windowOpen,
                                            ])></span>
                                            {{ $windowOpen ? 'مفتوحة' : 'مغلقة' }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="font-mono font-semibold text-ink">{{ $conversation->messages_count }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-ink-2">
                                        @php $last = $conversation->latestMessage?->created_at ?? $conversation->updated_at; @endphp
                                        <span title="{{ $last?->format('Y-m-d H:i') }}">{{ $last?->diffForHumans() ?? '—' }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-end">
                                        <a href="{{ route('conversations.show', $conversation) }}" class="inline-flex items-center gap-1 rounded-lg border border-ink/10 px-3 py-1.5 text-xs font-semibold text-emerald transition hover:bg-emerald/5">
                                            عرض
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-card>

            <div>{{ $conversations->links() }}</div>
        @endif
    </div>
</x-app-layout>
