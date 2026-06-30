{{--
    Super-admin layout (§1, §13). DELIBERATELY standalone from the tenant
    layout (layouts/app.blade.php): the admin crosses every tenant and must
    never share chrome, nav, or context with a tenant-scoped page. A gold-on-
    night palette visually marks "you are in the platform console", so an admin
    is never confused about which surface they are operating on.

    No tenant data, no TenantContext — every figure shown here was gathered in an
    admin controller via withoutGlobalScopes() (the one audited exception, §1).
--}}
@php
    $adminNav = [
        ['route' => 'admin.dashboard', 'label' => 'لوحة الأدمن', 'active' => 'admin.dashboard', 'icon' => 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 8.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25A2.25 2.25 0 0 1 13.5 8.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z'],
        ['route' => 'admin.customers.index', 'label' => 'العملاء', 'active' => 'admin.customers.*', 'icon' => 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z'],
        ['route' => 'admin.analytics.index', 'label' => 'التحليلات', 'active' => 'admin.analytics.*', 'icon' => 'M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z'],
        ['route' => 'admin.site.edit', 'label' => 'محتوى الموقع', 'active' => 'admin.site.*', 'icon' => 'M12 21a9.004 9.004 0 0 0 8.716-6.747M12 21a9.004 9.004 0 0 1-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 0 1 7.843 4.582M12 3a8.997 8.997 0 0 0-7.843 4.582m15.686 0A11.953 11.953 0 0 1 12 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0 1 21 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0 1 12 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 0 1 3 12c0-1.605.42-3.113 1.157-4.418'],
        ['route' => 'admin.settings.edit', 'label' => 'الإعدادات', 'active' => 'admin.settings.*', 'icon' => 'M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z'],
    ];
@endphp
<!DOCTYPE html>
<html dir="rtl" lang="ar">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ ($title ?? 'لوحة الأدمن') }} · {{ config('app.name', 'وريد') }}</title>

        <!-- Fonts: IBM Plex Sans Arabic -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div x-data="{ sidebar: false }" class="min-h-screen bg-paper">
            <!-- Mobile overlay -->
            <div
                x-show="sidebar"
                x-transition.opacity
                @click="sidebar = false"
                class="fixed inset-0 z-30 bg-night/50 backdrop-blur-sm lg:hidden"
                style="display:none"
            ></div>

            <!-- Admin sidebar: gold-accented to mark the platform console -->
            <aside
                class="bg-night-gradient fixed inset-y-0 right-0 z-40 flex w-72 flex-col border-s-2 border-gold/40 transition-transform duration-300 ease-in-out lg:translate-x-0"
                :class="sidebar ? 'translate-x-0' : 'translate-x-full'"
            >
                <div class="bg-night-grid pointer-events-none absolute inset-0 opacity-50"></div>

                <!-- Brand -->
                <div class="relative flex h-16 items-center justify-between border-b border-white/10 px-6">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3">
                        <span class="grid h-9 w-9 place-items-center rounded-lg border border-gold/30 bg-gold/15 text-gold">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.48 3.499a.562.562 0 0 1 1.04 0l2.125 5.111a.563.563 0 0 0 .475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 0 0-.182.557l1.285 5.385a.562.562 0 0 1-.84.61l-4.725-2.885a.562.562 0 0 0-.586 0L6.982 20.54a.562.562 0 0 1-.84-.61l1.285-5.386a.562.562 0 0 0-.182-.557l-4.204-3.602a.562.562 0 0 1 .321-.988l5.518-.442a.563.563 0 0 0 .475-.345L11.48 3.5Z" />
                            </svg>
                        </span>
                        <span class="flex flex-col leading-tight">
                            <span class="text-xl font-bold tracking-tight text-white">وريد</span>
                            <span class="font-mono text-[10px] uppercase tracking-widest text-gold-soft">Super-Admin</span>
                        </span>
                    </a>
                    <button @click="sidebar = false" class="text-white/60 transition hover:text-white lg:hidden" aria-label="إغلاق القائمة">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- Nav -->
                <nav class="relative flex-1 space-y-1 overflow-y-auto px-4 py-6">
                    @foreach ($adminNav as $item)
                        @php $isActive = request()->routeIs($item['active']); @endphp
                        <a
                            href="{{ route($item['route']) }}"
                            @class([
                                'group flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition',
                                'bg-gold/15 text-white shadow-luxe ring-1 ring-inset ring-gold/30' => $isActive,
                                'text-white/65 hover:bg-white/5 hover:text-white' => ! $isActive,
                            ])
                        >
                            <svg @class([
                                    'h-5 w-5 shrink-0 transition',
                                    'text-gold-soft' => $isActive,
                                    'text-white/45 group-hover:text-white/80' => ! $isActive,
                                ]) fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $item['icon'] }}" />
                            </svg>
                            <span>{{ $item['label'] }}</span>
                            @if ($isActive)
                                <span class="me-auto h-1.5 w-1.5 rounded-full bg-gold-soft"></span>
                            @endif
                        </a>
                    @endforeach
                </nav>

                <!-- Footer: admin identity + logout -->
                <div class="relative border-t border-white/10 p-4">
                    <div class="mb-3 flex items-center gap-3 px-2">
                        <div class="grid h-9 w-9 place-items-center rounded-full bg-gold/15 font-semibold text-gold-soft">
                            {{ mb_substr(Auth::user()->name, 0, 1) }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-white">{{ Auth::user()->name }}</div>
                            <div class="truncate font-mono text-[11px] text-white/50">{{ Auth::user()->email }}</div>
                        </div>
                    </div>
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

            <!-- Main column (sidebar sits at right-0 in RTL → pad the inline-start/right) -->
            <div class="lg:ps-72">
                <!-- Topbar -->
                <header class="sticky top-0 z-20 border-b border-ink/10 bg-paper/80 backdrop-blur">
                    <div class="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <button
                            @click="sidebar = true"
                            class="grid h-10 w-10 place-items-center rounded-lg border border-ink/10 bg-white text-ink-2 transition hover:bg-paper lg:hidden"
                            aria-label="فتح القائمة"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
                            </svg>
                        </button>

                        @isset($header)
                            <div class="min-w-0 flex-1">{{ $header }}</div>
                        @else
                            <div class="flex-1"></div>
                        @endisset

                        <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/30 bg-gold/10 px-3 py-1 text-xs font-semibold text-gold">
                            <span class="h-1.5 w-1.5 rounded-full bg-gold"></span>
                            وضع الإدارة
                        </span>
                    </div>
                </header>

                <!-- Flash status -->
                @if (session('status'))
                    <div class="px-4 pt-6 sm:px-6 lg:px-8">
                        <div class="mx-auto flex max-w-5xl items-center gap-3 rounded-xl border border-signal/30 bg-signal/10 px-4 py-3 text-sm font-medium text-emerald-deep">
                            <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <span>{{ __('messages.status.'.session('status')) }}</span>
                        </div>
                    </div>
                @endif

                <main class="px-4 py-8 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-5xl">
                        {{ $slot }}
                    </div>
                </main>
            </div>
        </div>
    </body>
</html>
