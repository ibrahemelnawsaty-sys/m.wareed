{{--
    Tenant-facing account-status banner (§5, §10). Shows the signed-in customer
    ONLY their OWN tenant's state — never admin data, never another customer.

    The tenant is read from the authenticated user's own relationship; there is
    no cross-tenant lookup here. If anything is missing we render nothing rather
    than guess. Money/secrets are irrelevant to this component — it shows status
    only.
--}}
@php
    /** @var \App\Models\User|null $user */
    $user = auth()->user();
    /** @var \App\Models\Tenant|null $tenant */
    $tenant = $user?->tenant;

    $state = null; // one of: pending | suspended | expired | active

    if ($tenant !== null) {
        if ($tenant->status === 'pending') {
            $state = 'pending';
        } elseif ($tenant->status === 'suspended') {
            $state = 'suspended';
        } elseif (
            $tenant->status === 'active'
            && $tenant->subscription_ends_at !== null
            && $tenant->subscription_ends_at->isPast()
        ) {
            $state = 'expired';
        } elseif ($tenant->status === 'active') {
            $state = 'active';
        }
    }
@endphp

@if ($state === 'pending')
    <div class="flex items-start gap-3 rounded-2xl border border-gold/30 bg-gold/10 p-4 shadow-luxe">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gold/20 text-gold">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
        </span>
        <div>
            <p class="text-sm font-bold text-ink">حسابك قيد المراجعة</p>
            <p class="mt-0.5 text-sm text-ink-soft">سيُفعّل بوتك بعد موافقة الإدارة على حسابك.</p>
        </div>
    </div>
@elseif ($state === 'suspended')
    <div class="flex items-start gap-3 rounded-2xl border border-[#B5462F]/30 bg-[#B5462F]/5 p-4 shadow-luxe">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#B5462F]/10 text-[#B5462F]">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>
        </span>
        <div>
            <p class="text-sm font-bold text-ink">حسابك موقوف مؤقتاً</p>
            <p class="mt-0.5 text-sm text-ink-soft">تواصل مع الدعم لإعادة تفعيل حسابك.</p>
        </div>
    </div>
@elseif ($state === 'expired')
    <div class="flex items-start gap-3 rounded-2xl border border-gold/30 bg-gold/10 p-4 shadow-luxe">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gold/20 text-gold">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
        </span>
        <div>
            <p class="text-sm font-bold text-ink">انتهى اشتراكك</p>
            <p class="mt-0.5 text-sm text-ink-soft">جدّد اشتراكك لإعادة تفعيل البوت والرد على عملائك.</p>
        </div>
    </div>
@elseif ($state === 'active')
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-signal/30 bg-signal/10 p-4 shadow-luxe">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-signal/20 text-emerald-deep">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
            </span>
            <p class="text-sm font-bold text-emerald-deep">حسابك نشط</p>
        </div>
        @if ($tenant->subscription_ends_at !== null)
            <p class="font-mono text-xs text-ink-soft">ينتهي الاشتراك في {{ $tenant->subscription_ends_at->format('Y-m-d') }}</p>
        @endif
    </div>
@endif
