@props(['status'])

{{-- Coloured tenant-status pill, shared across the admin screens. --}}
@php
    $label = match ($status) {
        'pending' => 'قيد المراجعة',
        'active' => 'نشط',
        'suspended' => 'موقوف',
        default => $status,
    };
@endphp

<span @class([
    'inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium',
    'bg-gold/15 text-gold' => $status === 'pending',
    'bg-signal/15 text-emerald-deep' => $status === 'active',
    'bg-[#B5462F]/10 text-[#B5462F]' => $status === 'suspended',
    'bg-ink/5 text-ink-soft' => ! in_array($status, ['pending', 'active', 'suspended'], true),
])>
    <span @class([
        'h-1.5 w-1.5 rounded-full',
        'bg-gold' => $status === 'pending',
        'bg-emerald' => $status === 'active',
        'bg-[#B5462F]' => $status === 'suspended',
        'bg-ink/30' => ! in_array($status, ['pending', 'active', 'suspended'], true),
    ])></span>
    {{ $label }}
</span>
