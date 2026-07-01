@php
    // Sidebar nav. Items flagged `owner => true` are account-administration
    // surfaces (WhatsApp connection, bot config, knowledge base, metered
    // playground, team) — hidden from agents in the nav AND gated by the `owner`
    // middleware at the route level (least privilege, §1/§13). Agents see only
    // the shared surfaces: dashboard, conversations, analytics, profile.
    $isOwner = auth()->user()?->isOwner() ?? false;

    $allItems = [
        ['route' => 'dashboard',       'owner' => false, 'label' => 'لوحة التحكم', 'active' => 'dashboard',  'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 8.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25A2.25 2.25 0 0 1 13.5 8.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z'],
        ['route' => 'inbox.index',     'owner' => false, 'label' => 'صندوق الوارد', 'active' => 'inbox.*', 'icon' => 'M2.25 13.5h3.86a2.25 2.25 0 0 1 2.012 1.244l.256.512a2.25 2.25 0 0 0 2.013 1.244h3.218a2.25 2.25 0 0 0 2.013-1.244l.256-.512a2.25 2.25 0 0 1 2.013-1.244h3.859m-19.5.338V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18v-4.162c0-.224-.034-.447-.1-.661L19.24 5.338a2.25 2.25 0 0 0-2.15-1.588H6.911a2.25 2.25 0 0 0-2.15 1.588L2.35 13.177a2.25 2.25 0 0 0-.1.661Z'],
        ['route' => 'whatsapp.edit',   'owner' => true,  'label' => 'ربط واتساب',  'active' => 'whatsapp.*', 'icon' => 'M7.5 21 3 16.5l1.2-3.6A8.25 8.25 0 1 1 7.5 21Z'],
        ['route' => 'bot.edit',        'owner' => true,  'label' => 'إعداد البوت', 'active' => 'bot.*',      'icon' => 'M9.75 3.104v5.714a2.25 2.25 0 0 1-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 0 1 4.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0 1 12 15a9.065 9.065 0 0 0-6.23-.693L5 14.5m14.8.8 1.402 1.402c1.232 1.232.65 3.318-1.067 3.611A48.309 48.309 0 0 1 12 21c-2.773 0-5.491-.235-8.135-.687-1.718-.293-2.3-2.379-1.067-3.61L5 14.5'],
        ['route' => 'menu.edit',       'owner' => true,  'label' => 'قائمة الخدمات', 'active' => 'menu.*', 'icon' => 'M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5'],
        ['route' => 'knowledge.index', 'owner' => true,  'label' => 'قاعدة المعرفة', 'active' => 'knowledge.*', 'icon' => 'M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25'],
        ['route' => 'conversations.index', 'owner' => false, 'label' => 'المحادثات', 'active' => 'conversations.*', 'icon' => 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z'],
        ['route' => 'analytics.index', 'owner' => false, 'label' => 'التحليلات', 'active' => 'analytics.*', 'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z'],
        ['route' => 'playground.index', 'owner' => true,  'label' => 'مختبر البوت', 'active' => 'playground.*', 'icon' => 'M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.456-2.456L14.25 6l1.035-.259a3.375 3.375 0 0 0 2.456-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 0 0-2.456 2.456Z'],
        ['route' => 'team.index',      'owner' => true,  'label' => 'الفريق', 'active' => 'team.*', 'icon' => 'M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z'],
        ['route' => 'templates.index', 'owner' => true,  'label' => 'القوالب المعتمدة', 'active' => 'templates.*', 'icon' => 'M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25Z'],
        ['route' => 'bulk.index',      'owner' => true,  'label' => 'الرسائل الجماعية', 'active' => 'bulk.*', 'icon' => 'M2.25 12 11.204 2.83a.75.75 0 0 1 1.092 0L21.25 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'],
        ['route' => 'profile.edit',    'owner' => false, 'label' => 'الملف الشخصي', 'active' => 'profile.*',  'icon' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z'],
    ];

    $navItems = array_values(array_filter($allItems, fn ($item) => $isOwner || ! $item['owner']));
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
