<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl border border-ink/15 bg-white px-5 py-2.5 text-sm font-semibold text-ink-2 shadow-sm transition duration-150 ease-in-out hover:bg-paper focus:outline-none focus:ring-2 focus:ring-emerald/30 focus:ring-offset-2 disabled:opacity-50']) }}>
    {{ $slot }}
</button>
