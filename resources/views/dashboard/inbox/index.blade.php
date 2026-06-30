<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">صندوق الوارد</h1>
            <p class="text-sm text-ink-soft">محادثات عملائك عبر واتساب — استلمها وردّ عليها يدوياً.</p>
        </div>
    </x-slot>

    @php
        $tabs = [
            'all' => 'الكل',
            'human' => 'محوّلة للموظفين',
            'mine' => 'مهامي',
            'unassigned' => 'غير مسندة',
        ];
    @endphp

    <div class="space-y-6">
        {{-- Filter tabs with counters --}}
        <div class="flex flex-wrap gap-2">
            @foreach ($tabs as $key => $label)
                @php $isActive = $filter === $key; @endphp
                <a
                    href="{{ route('inbox.index', ['filter' => $key]) }}"
                    @class([
                        'inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-medium transition',
                        'border-emerald/30 bg-emerald/10 text-emerald-deep' => $isActive,
                        'border-ink/10 bg-white text-ink-2 hover:bg-paper' => ! $isActive,
                    ])
                >
                    <span>{{ $label }}</span>
                    <span @class([
                        'inline-flex min-w-[1.5rem] justify-center rounded-full px-1.5 py-0.5 text-[11px] font-mono',
                        'bg-emerald text-white' => $isActive,
                        'bg-ink/5 text-ink-soft' => ! $isActive,
                    ])>{{ $counts[$key] ?? 0 }}</span>
                </a>
            @endforeach
        </div>

        @if ($conversations->isEmpty())
            <div class="rounded-2xl border border-dashed border-ink/15 bg-white p-12 text-center shadow-sm">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald/10 text-emerald">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z" /></svg>
                </span>
                <h3 class="mt-4 text-base font-bold text-ink">لا توجد محادثات هنا</h3>
                <p class="mx-auto mt-1 max-w-sm text-sm text-ink-soft">ستظهر هنا محادثات عملائك مع بوتك فور بدء التفاعل عبر واتساب.</p>
            </div>
        @else
            <x-card class="overflow-hidden !p-0">
                <ul class="divide-y divide-ink/5">
                    @foreach ($conversations as $conversation)
                        @php
                            $display = $conversation->contact_name ?: $conversation->wa_contact_id;
                            $last = $conversation->latestMessage;
                            $lastAt = $last?->created_at ?? $conversation->updated_at;
                        @endphp
                        <li>
                            <a href="{{ route('inbox.show', $conversation) }}" class="flex items-center gap-4 px-6 py-4 transition hover:bg-paper/60">
                                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-emerald/10 font-semibold text-emerald">
                                    {{ mb_substr($display, 0, 1) }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="truncate font-semibold text-ink">{{ $display }}</span>
                                        <span class="shrink-0 font-mono text-[11px] text-ink-soft" title="{{ $lastAt?->format('Y-m-d H:i') }}">{{ $lastAt?->diffForHumans() ?? '—' }}</span>
                                    </div>
                                    <div class="mt-1 flex items-center justify-between gap-3">
                                        <span class="truncate text-sm text-ink-soft">{{ \Illuminate\Support\Str::limit($last?->body ?? 'لا توجد رسائل بعد', 60) }}</span>
                                        <span class="shrink-0">
                                            @if ($conversation->isHumanMode())
                                                @if ($conversation->assignedTo)
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-700">
                                                        <span aria-hidden="true">&#128100;</span> مُسند: {{ $conversation->assignedTo->name }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-medium text-rose-700">
                                                        <span aria-hidden="true">&#128100;</span> غير مسندة
                                                    </span>
                                                @endif
                                            @else
                                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald/10 px-2.5 py-0.5 text-[11px] font-medium text-emerald-deep">
                                                    <span aria-hidden="true">&#129302;</span> آلي
                                                </span>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-card>

            <div>{{ $conversations->links() }}</div>
        @endif
    </div>
</x-app-layout>
