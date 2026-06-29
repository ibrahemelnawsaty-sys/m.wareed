@php
    $navItems = [
        ['route' => 'dashboard',       'label' => 'لوحة التحكم', 'active' => 'dashboard',  'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 8.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25A2.25 2.25 0 0 1 13.5 8.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z'],
        ['route' => 'whatsapp.edit',   'label' => 'ربط واتساب',  'active' => 'whatsapp.*', 'icon' => 'M7.5 21 3 16.5l1.2-3.6A8.25 8.25 0 1 1 7.5 21Z'],
        ['route' => 'bot.edit',        'label' => 'إعداد البوت', 'active' => 'bot.*',      'icon' => 'M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5'],
        ['route' => 'knowledge.index', 'label' => 'قاعدة المعرفة', 'active' => 'knowledge.*', 'icon' => 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25'],
        ['route' => 'profile.edit',    'label' => 'الملف الشخصي', 'active' => 'profile.*',  'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z'],
    ];
@endphp

<aside
    class="bg-night-gradient fixed inset-y-0 right-0 z-40 flex w-72 flex-col transition-transform duration-300 ease-in-out lg:translate-x-0"
    :class="sidebar ? 'translate-x-0' : 'translate-x-full'"
>
    <div class="bg-night-grid pointer-events-none absolute inset-0 opacity-50"></div>

    <!-- Brand -->
    <div class="relative flex h-16 items-center justify-between border-b border-white/10 px-6">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <span class="grid h-9 w-9 place-items-center rounded-lg border border-white/10 bg-signal/15 text-signal">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5l1.2-3.6A8.25 8.25 0 1 1 7.5 21Z" />
                </svg>
            </span>
            <span class="text-xl font-bold tracking-tight text-white">وريد</span>
        </a>
        <button @click="sidebar = false" class="text-white/60 transition hover:text-white lg:hidden" aria-label="إغلاق القائمة">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Nav -->
    <nav class="relative flex-1 space-y-1 overflow-y-auto px-4 py-6">
        @foreach ($navItems as $item)
            @php $isActive = request()->routeIs($item['active']); @endphp
            <a
                href="{{ route($item['route']) }}"
                @class([
                    'group flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition',
                    'bg-signal/15 text-white shadow-luxe ring-1 ring-inset ring-signal/30' => $isActive,
                    'text-white/65 hover:bg-white/5 hover:text-white' => ! $isActive,
                ])
            >
                <svg @class([
                        'h-5 w-5 shrink-0 transition',
                        'text-signal' => $isActive,
                        'text-white/45 group-hover:text-white/80' => ! $isActive,
                    ]) fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                </svg>
                <span>{{ $item['label'] }}</span>
                @if ($isActive)
                    <span class="me-auto h-1.5 w-1.5 rounded-full bg-signal"></span>
                @endif
            </a>
        @endforeach
    </nav>

    <!-- Footer / logout -->
    <div class="relative border-t border-white/10 p-4">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-white/65 transition hover:bg-white/5 hover:text-white">
                <svg class="h-5 w-5 shrink-0 text-white/45" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15M12 9l-3 3m0 0 3 3m-3-3h12.75" />
                </svg>
                <span>تسجيل الخروج</span>
            </button>
        </form>
    </div>
</aside>
