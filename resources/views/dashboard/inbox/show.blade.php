<x-app-layout>
    @php
        $windowOpen = $conversation->isWindowOpen();
        $display = $conversation->contact_name ?: $conversation->wa_contact_id;
        $user = auth()->user();
        $isHuman = $conversation->isHumanMode();
        $assigned = $conversation->assignedTo;
        // The owner may always act; an agent only on a conversation that is
        // theirs or not yet assigned (mirrors the controller authorization §13).
        $canAct = $user->isOwner()
            || ! $conversation->isAssigned()
            || $conversation->isAssignedTo($user);
        // The latest message id the page already rendered — the poller asks for
        // anything newer than this.
        $lastId = $messages->last()?->id ?? 0;
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <a href="{{ route('inbox.index') }}" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-ink/10 text-ink-2 transition hover:bg-paper" aria-label="رجوع">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0-6.75-6.75M19.5 12l-6.75 6.75" /></svg>
                </a>
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald/10 font-semibold text-emerald">{{ mb_substr($display, 0, 1) }}</span>
                <div class="min-w-0">
                    <h1 class="truncate text-base font-bold text-ink">{{ $display }}</h1>
                    <p class="truncate font-mono text-[11px] text-ink-soft" dir="ltr">{{ $conversation->wa_contact_id }}</p>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-2">
                @if ($isHuman)
                    <span class="hidden items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-medium text-amber-700 sm:inline-flex">
                        <span aria-hidden="true">&#128100;</span>
                        {{ $assigned ? 'مُسند: '.$assigned->name : 'موظف — غير مسند' }}
                    </span>
                @else
                    <span class="hidden items-center gap-1 rounded-full bg-emerald/10 px-2.5 py-1 text-[11px] font-medium text-emerald-deep sm:inline-flex">
                        <span aria-hidden="true">&#129302;</span> آلي
                    </span>
                @endif

                @if (! $conversation->isAssigned())
                    <form method="POST" action="{{ route('inbox.claim', $conversation) }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-emerald/30 bg-emerald/5 px-3 py-1.5 text-xs font-semibold text-emerald transition hover:bg-emerald/10">استلام</button>
                    </form>
                @endif

                @if ($isHuman && $canAct)
                    <form method="POST" action="{{ route('inbox.release', $conversation) }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-ink/10 px-3 py-1.5 text-xs font-semibold text-ink-2 transition hover:bg-paper">إرجاع للبوت</button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div
        x-data="inboxThread({
            url: '{{ route('inbox.messages', $conversation) }}',
            lastId: {{ $lastId }},
        })"
        x-init="init()"
        class="flex h-[calc(100vh-9rem)] flex-col overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-luxe"
    >
        {{-- Chat background + bubbles --}}
        <div x-ref="scroll" class="flex-1 space-y-3 overflow-y-auto bg-paper/40 p-4 sm:p-6">
            @forelse ($messages as $message)
                @php $isOut = $message->direction === 'out'; @endphp
                <div @class(['flex', 'justify-start' => $isOut, 'justify-end' => ! $isOut])>
                    <div class="max-w-[80%] sm:max-w-[65%]">
                        <div @class([
                            'rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm',
                            'bg-emerald text-white rounded-bl-md' => $isOut,
                            'bg-white text-ink rounded-br-md border border-ink/10' => ! $isOut,
                        ])>
                            <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                        </div>
                        <div @class([
                            'mt-1 flex items-center gap-2 px-1 text-[11px] text-ink-soft',
                            'justify-start' => $isOut,
                            'justify-end' => ! $isOut,
                        ])>
                            <span>{{ $isOut ? ($message->user?->name ?? 'البوت') : 'العميل' }}</span>
                            <span aria-hidden="true">·</span>
                            <span class="font-mono" dir="ltr">{{ $message->created_at?->format('Y-m-d H:i') }}</span>
                        </div>
                    </div>
                </div>
            @empty
                <div class="grid h-full place-items-center text-center">
                    <p class="text-sm text-ink-soft">لا توجد رسائل في هذه المحادثة بعد.</p>
                </div>
            @endforelse

            {{-- Newly polled messages are appended here by Alpine --}}
            <template x-for="m in newMessages" :key="m.id">
                <div class="flex" :class="m.direction === 'out' ? 'justify-start' : 'justify-end'">
                    <div class="max-w-[80%] sm:max-w-[65%]">
                        <div
                            class="rounded-2xl px-4 py-2.5 text-sm leading-relaxed shadow-sm"
                            :class="m.direction === 'out' ? 'bg-emerald text-white rounded-bl-md' : 'bg-white text-ink rounded-br-md border border-ink/10'"
                        >
                            <p class="whitespace-pre-wrap break-words" x-text="m.body"></p>
                        </div>
                        <div
                            class="mt-1 flex items-center gap-2 px-1 text-[11px] text-ink-soft"
                            :class="m.direction === 'out' ? 'justify-start' : 'justify-end'"
                        >
                            <span x-text="m.author"></span>
                            <span aria-hidden="true">·</span>
                            <span class="font-mono" dir="ltr" x-text="m.created_at"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        {{-- Composer --}}
        <div class="border-t border-ink/10 bg-white p-3 sm:p-4">
            @error('reply')
                <p class="mb-2 text-sm text-red-600">{{ $message }}</p>
            @enderror

            @if (! $windowOpen)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    انتهت نافذة 24 ساعة؛ لا يمكن إرسال رسالة حرة. يلزم قالب معتمد لإعادة بدء المحادثة.
                </div>
            @else
                <form method="POST" action="{{ route('inbox.reply', $conversation) }}" class="flex items-end gap-2">
                    @csrf
                    <textarea
                        name="body"
                        rows="1"
                        required
                        maxlength="4096"
                        placeholder="اكتب رسالتك…"
                        class="max-h-32 min-h-[2.75rem] flex-1 resize-none rounded-xl border border-ink/15 bg-paper/40 px-4 py-2.5 text-sm text-ink focus:border-emerald focus:ring-emerald"
                        @keydown.enter.prevent="$event.shiftKey || $el.closest('form').requestSubmit()"
                    >{{ old('body') }}</textarea>
                    <button type="submit" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald text-white transition hover:bg-emerald-deep" aria-label="إرسال">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" /></svg>
                    </button>
                </form>
            @endif
        </div>
    </div>

    <script>
            function inboxThread(config) {
                return {
                    url: config.url,
                    lastId: config.lastId,
                    newMessages: [],
                    init() {
                        this.scrollToBottom();
                        // Poll every 5s — no WebSockets on shared hosting (ADR-03).
                        setInterval(() => this.poll(), 5000);
                    },
                    async poll() {
                        try {
                            const res = await fetch(`${this.url}?after=${this.lastId}`, {
                                headers: { 'Accept': 'application/json' },
                            });
                            if (!res.ok) return;
                            const data = await res.json();
                            if (Array.isArray(data) && data.length) {
                                for (const m of data) {
                                    this.newMessages.push(m);
                                    this.lastId = m.id;
                                }
                                this.$nextTick(() => this.scrollToBottom());
                            }
                        } catch (e) {
                            // Network blip — try again on the next tick. No noise.
                        }
                    },
                    scrollToBottom() {
                        const el = this.$refs.scroll;
                        if (el) el.scrollTop = el.scrollHeight;
                    },
                };
            }
    </script>
</x-app-layout>
