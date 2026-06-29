<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <a href="{{ route('conversations.index') }}" class="grid h-9 w-9 place-items-center rounded-lg border border-ink/10 text-ink-2 transition hover:bg-paper" aria-label="رجوع">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h15m0 0-6.75-6.75M19.5 12l-6.75 6.75" /></svg>
            </a>
            <div>
                <h1 class="text-lg font-bold text-ink">
                    محادثة <span class="font-mono" dir="ltr">{{ $conversation->wa_contact_id }}</span>
                </h1>
                <p class="text-sm text-ink-soft">
                    @php $windowOpen = $conversation->isWindowOpen(); @endphp
                    {{ $messages->count() }} رسالة ·
                    <span @class(['font-medium', 'text-emerald' => $windowOpen, 'text-ink-soft' => ! $windowOpen])>
                        النافذة {{ $windowOpen ? 'مفتوحة' : 'مغلقة' }}
                    </span>
                </p>
            </div>
        </div>
    </x-slot>

    <x-card class="!p-0">
        @if ($messages->isEmpty())
            <div class="p-12 text-center">
                <p class="text-sm text-ink-soft">لا توجد رسائل في هذه المحادثة بعد.</p>
            </div>
        @else
            <div class="space-y-4 p-6">
                @foreach ($messages as $message)
                    @php $isOut = $message->direction === 'out'; @endphp
                    {{-- Inbound (customer) on the start side; outbound (bot) on the
                         end side with an emerald bubble (RTL §5). --}}
                    <div @class(['flex', 'justify-end' => $isOut, 'justify-start' => ! $isOut])>
                        <div class="max-w-[80%] sm:max-w-[70%]">
                            <div @class([
                                'rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-sm',
                                'bg-emerald text-white rounded-bl-md' => $isOut,
                                'bg-paper text-ink rounded-br-md border border-ink/10' => ! $isOut,
                            ])>
                                <p class="whitespace-pre-wrap break-words">{{ $message->body }}</p>
                            </div>
                            <div @class([
                                'mt-1 flex items-center gap-2 px-1 text-[11px] text-ink-soft',
                                'justify-end' => $isOut,
                                'justify-start' => ! $isOut,
                            ])>
                                <span>{{ $isOut ? 'البوت' : 'العميل' }}</span>
                                <span aria-hidden="true">·</span>
                                <span class="font-mono" dir="ltr">{{ $message->created_at?->format('Y-m-d H:i') }}</span>
                                @if ($isOut && ($message->tokens_in || $message->tokens_out))
                                    <span aria-hidden="true">·</span>
                                    <span class="font-mono" title="توكنز الإدخال/الإخراج">
                                        {{ $message->tokens_in }}↓ {{ $message->tokens_out }}↑
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>
</x-app-layout>
