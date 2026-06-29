@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'rounded-xl border-ink/15 bg-white text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-emerald focus:ring-emerald/30 disabled:bg-paper disabled:text-ink-soft']) }}>
