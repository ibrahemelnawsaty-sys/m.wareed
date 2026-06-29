<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">مختبر البوت</h1>
            <p class="text-sm text-ink-soft">جرّب ردود بوتك مباشرةً دون واتساب. هذه التجربة عابرة ولا تُحفظ.</p>
        </div>
    </x-slot>

    <div
        x-data="playground()"
        class="mx-auto flex h-[calc(100vh-12rem)] max-w-3xl flex-col overflow-hidden rounded-2xl border border-ink/10 bg-white shadow-luxe"
    >
        <!-- Thread -->
        <div class="flex-1 space-y-4 overflow-y-auto p-6" x-ref="thread">
            <template x-if="messages.length === 0">
                <div class="grid h-full place-items-center text-center">
                    <div>
                        <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald/10 text-emerald">
                            <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.456-2.456L14.25 6l1.035-.259a3.375 3.375 0 0 0 2.456-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z" /></svg>
                        </span>
                        <h3 class="mt-4 text-base font-bold text-ink">ابدأ بتجربة بوتك</h3>
                        <p class="mx-auto mt-1 max-w-xs text-sm text-ink-soft">اكتب رسالة كما لو كنت أحد عملائك وشاهد كيف يردّ بوتك.</p>
                    </div>
                </div>
            </template>

            <template x-for="(msg, i) in messages" :key="i">
                <div class="flex" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div class="max-w-[80%] sm:max-w-[70%]">
                        <div
                            class="rounded-2xl px-4 py-3 text-sm leading-relaxed shadow-sm"
                            :class="msg.role === 'user'
                                ? 'bg-emerald text-white rounded-bl-md'
                                : (msg.error ? 'bg-[#B5462F]/10 text-[#B5462F] rounded-br-md border border-[#B5462F]/20' : 'bg-paper text-ink rounded-br-md border border-ink/10')"
                        >
                            <p class="whitespace-pre-wrap break-words" x-text="msg.text"></p>
                        </div>
                        <div
                            class="mt-1 flex items-center gap-2 px-1 text-[11px] text-ink-soft"
                            :class="msg.role === 'user' ? 'justify-end' : 'justify-start'"
                        >
                            <span x-text="msg.role === 'user' ? 'أنت' : 'البوت'"></span>
                            <template x-if="msg.tokens">
                                <span class="font-mono" dir="ltr" x-text="`${msg.tokens.in}↓ ${msg.tokens.out}↑`"></span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Typing indicator -->
            <template x-if="loading">
                <div class="flex justify-start">
                    <div class="rounded-2xl rounded-br-md border border-ink/10 bg-paper px-4 py-3 shadow-sm">
                        <div class="flex items-center gap-1">
                            <span class="h-2 w-2 animate-bounce rounded-full bg-ink/40" style="animation-delay: 0ms;"></span>
                            <span class="h-2 w-2 animate-bounce rounded-full bg-ink/40" style="animation-delay: 150ms;"></span>
                            <span class="h-2 w-2 animate-bounce rounded-full bg-ink/40" style="animation-delay: 300ms;"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Composer -->
        <form @submit.prevent="send" class="border-t border-ink/10 bg-white p-4">
            <div class="flex items-end gap-3">
                <textarea
                    x-model="input"
                    @keydown.enter.prevent="send"
                    rows="1"
                    placeholder="اكتب رسالة العميل هنا..."
                    class="block w-full resize-none rounded-xl border-ink/15 bg-paper/50 px-4 py-3 text-sm text-ink shadow-sm focus:border-emerald focus:ring-emerald"
                    :disabled="loading"
                ></textarea>
                <button
                    type="submit"
                    class="inline-flex h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-emerald px-5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="loading || input.trim() === ''"
                >
                    <span x-show="!loading">إرسال</span>
                    <span x-show="loading">...</span>
                </button>
            </div>
            <p class="mt-2 px-1 text-[11px] text-ink-soft">تجربة عابرة لا تُحفظ ولا تُحتسب ضمن استهلاكك.</p>
        </form>
    </div>

    @push('scripts')
    @endpush

    <script>
        function playground() {
            return {
                input: '',
                loading: false,
                messages: [],
                send() {
                    const text = this.input.trim();
                    if (text === '' || this.loading) {
                        return;
                    }

                    this.messages.push({ role: 'user', text });
                    this.input = '';
                    this.loading = true;
                    this.$nextTick(() => this.scrollDown());

                    fetch('{{ route('playground.send') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({ message: text }),
                    })
                        .then(async (res) => {
                            const data = await res.json().catch(() => ({}));
                            if (!res.ok) {
                                throw new Error(data.message || 'تعذّر الحصول على رد. حاول مرة أخرى.');
                            }
                            this.messages.push({
                                role: 'model',
                                text: data.reply,
                                tokens: { in: data.tokens_in ?? 0, out: data.tokens_out ?? 0 },
                            });
                        })
                        .catch((err) => {
                            this.messages.push({ role: 'model', text: err.message, error: true });
                        })
                        .finally(() => {
                            this.loading = false;
                            this.$nextTick(() => this.scrollDown());
                        });
                },
                scrollDown() {
                    const el = this.$refs.thread;
                    if (el) {
                        el.scrollTop = el.scrollHeight;
                    }
                },
            };
        }
    </script>
</x-app-layout>
