@props(['micros' => null])

{{--
    Display-only cost rendering (§3). Storage and all arithmetic stay in integer
    micro-USD everywhere else; this component is the ONLY place we divide for the
    human eye, and it never feeds a computed value back into the system.

    Null-safe: a missing/empty counter renders as $0.00, never a PHP warning.
--}}
@php
    $micros = is_numeric($micros) ? (int) $micros : 0;
    // Integer dollars and remaining micro-cents, formatted to 2 decimals for
    // display. No float math is stored — this string is purely cosmetic.
    $display = number_format($micros / 1_000_000, 2);
@endphp

<span {{ $attributes->merge(['class' => 'font-mono tabular-nums']) }}>${{ $display }}</span>
