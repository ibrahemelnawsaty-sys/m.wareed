@props(['label', 'value'])

{{-- Read-only value with a one-click copy button (§10 — actionable UI). --}}
<div x-data="{ copied: false }">
    <span class="block text-sm font-semibold text-ink-2">{{ $label }}</span>
    <div class="mt-1.5 flex items-stretch overflow-hidden rounded-xl border border-ink/15 bg-paper">
        <input
            type="text"
            readonly
            dir="ltr"
            value="{{ $value }}"
            x-ref="src"
            class="min-w-0 flex-1 border-0 bg-transparent px-4 py-2.5 font-mono text-sm text-ink-2 focus:ring-0"
            @click="$refs.src.select()"
        >
        <button
            type="button"
            @click="navigator.clipboard.writeText($refs.src.value); copied = true; setTimeout(() => copied = false, 1800)"
            class="flex shrink-0 items-center gap-1.5 border-s border-ink/15 bg-white px-4 text-sm font-semibold text-emerald transition hover:bg-emerald/5"
        >
            <template x-if="!copied">
                <span class="flex items-center gap-1.5">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                    </svg>
                    نسخ
                </span>
            </template>
            <template x-if="copied">
                <span class="flex items-center gap-1.5 text-emerald-deep">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    تم النسخ
                </span>
            </template>
        </button>
    </div>
</div>
