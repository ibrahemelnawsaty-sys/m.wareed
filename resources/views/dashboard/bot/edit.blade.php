<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">إعداد البوت</h1>
            <p class="text-sm text-ink-soft">اضبط شخصية بوتك ودرجة إبداعه في الردود.</p>
        </div>
    </x-slot>

    <x-card title="سلوك البوت" subtitle="هذه التعليمات يعتمد عليها البوت عند الرد على عملائك.">
        <form method="POST" action="{{ route('bot.update') }}" class="space-y-6" x-data="{ temperature: {{ (int) old('temperature', $account->temperature) }} }">
            @csrf
            @method('PUT')

            <!-- Model (read-only, §12 — no switching) -->
            <div>
                <x-input-label :value="'النموذج الذكي'" />
                <div class="mt-1.5 flex items-center gap-3 rounded-xl border border-ink/15 bg-paper px-4 py-2.5">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-emerald/10 text-emerald">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z" /></svg>
                    </span>
                    <span class="font-mono text-sm font-semibold text-ink">{{ $account->ai_model }}</span>
                    <span class="me-auto rounded-full bg-emerald/10 px-3 py-1 text-xs font-medium text-emerald">ثابت</span>
                </div>
            </div>

            <!-- System prompt -->
            <div>
                <x-input-label for="system_prompt" :value="'تعليمات البوت (System Prompt)'" />
                <textarea
                    id="system_prompt"
                    name="system_prompt"
                    rows="8"
                    required
                    class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm leading-relaxed text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-emerald focus:ring-emerald/30"
                    placeholder="مثال: أنت مساعد خدمة عملاء لطيف ومحترف..."
                >{{ old('system_prompt', $account->system_prompt) }}</textarea>
                <x-input-error :messages="$errors->get('system_prompt')" class="mt-2" />
                <p class="mt-2 text-xs text-ink-soft">صِف شخصية البوت ولهجته ونطاق ما يجيب عنه.</p>
            </div>

            <!-- Temperature slider -->
            <div>
                <div class="flex items-center justify-between">
                    <x-input-label for="temperature" :value="'درجة الإبداع (Temperature)'" />
                    <span class="rounded-lg bg-emerald/10 px-3 py-1 font-mono text-sm font-bold text-emerald" x-text="temperature"></span>
                </div>
                <input
                    id="temperature"
                    name="temperature"
                    type="range"
                    min="0"
                    max="100"
                    step="1"
                    x-model="temperature"
                    class="mt-3 h-2 w-full cursor-pointer appearance-none rounded-full bg-paper accent-emerald"
                >
                <div class="mt-2 flex justify-between font-mono text-[11px] text-ink-soft">
                    <span>دقيق ومحافظ · 0</span>
                    <span>100 · مبدع ومتنوّع</span>
                </div>
                <x-input-error :messages="$errors->get('temperature')" class="mt-2" />
            </div>

            <div class="flex justify-end border-t border-ink/10 pt-5">
                <x-primary-button>حفظ إعدادات البوت</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
