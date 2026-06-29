<!DOCTYPE html>
<html dir="rtl" lang="ar">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'وريد') }}</title>

        <!-- Fonts: IBM Plex Sans Arabic -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;500;600&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div x-data="{ sidebar: false }" class="min-h-screen bg-paper">
            <!-- Mobile overlay -->
            <div
                x-show="sidebar"
                x-transition.opacity
                @click="sidebar = false"
                class="fixed inset-0 z-30 bg-night/40 backdrop-blur-sm lg:hidden"
                style="display:none"
            ></div>

            @include('layouts.navigation')

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

                        <div class="flex items-center gap-3">
                            <div class="hidden text-end sm:block">
                                <div class="text-sm font-semibold leading-tight text-ink">{{ Auth::user()->name }}</div>
                                <div class="font-mono text-[11px] text-ink-soft">{{ Auth::user()->email }}</div>
                            </div>
                            <div class="grid h-10 w-10 place-items-center rounded-full bg-emerald/10 font-semibold text-emerald">
                                {{ mb_substr(Auth::user()->name, 0, 1) }}
                            </div>
                        </div>
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
