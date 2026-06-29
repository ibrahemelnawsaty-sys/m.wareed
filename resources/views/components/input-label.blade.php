@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-ink-2']) }}>
    {{ $value ?? $slot }}
</label>
