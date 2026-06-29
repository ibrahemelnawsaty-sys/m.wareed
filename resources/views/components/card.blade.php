@props(['title' => null, 'subtitle' => null])

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-ink/10 bg-white shadow-luxe']) }}>
    @if ($title || $subtitle)
        <header class="border-b border-ink/10 px-6 py-5">
            @if ($title)
                <h2 class="text-base font-bold text-ink">{{ $title }}</h2>
            @endif
            @if ($subtitle)
                <p class="mt-1 text-sm text-ink-soft">{{ $subtitle }}</p>
            @endif
        </header>
    @endif

    <div class="p-6">
        {{ $slot }}
    </div>
</section>
