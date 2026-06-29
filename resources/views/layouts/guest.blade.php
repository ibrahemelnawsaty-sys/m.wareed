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
        <div class="min-h-screen lg:grid lg:grid-cols-2">
            <!-- Brand aside (night/emerald) -->
            <aside class="relative hidden overflow-hidden bg-night-gradient p-12 lg:flex lg:flex-col lg:justify-between">
                <div class="bg-night-grid pointer-events-none absolute inset-0 opacity-60"></div>

                <a href="/" class="relative flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-xl border border-white/10 bg-signal/15 text-signal">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5l1.2-3.6A8.25 8.25 0 1 1 7.5 21Z" />
                        </svg>
                    </span>
                    <span class="text-2xl font-bold tracking-tight text-white">وريد</span>
                </a>

                <div class="relative max-w-md">
                    <span class="mb-4 inline-flex items-center gap-2 rounded-full border border-white/10 px-4 py-1.5 font-mono text-[11px] uppercase tracking-[0.16em] text-signal">
                        <span class="pulse-dot h-1.5 w-1.5 rounded-full bg-signal"></span>
                        منصة بوتات واتساب الذكية
                    </span>
                    <h2 class="text-3xl font-bold leading-snug text-white">
                        بوت ذكي يرد على عملائك<br>
                        <span class="text-signal">آلياً، على مدار الساعة.</span>
                    </h2>
                    <p class="mt-4 text-[15px] leading-relaxed text-white/70">
                        اربط رقم واتساب عملك، أضِف قاعدة معرفتك، ودَع الذكاء الاصطناعي يتولّى الردود بلمسة عربية لطيفة واحترافية.
                    </p>
                </div>

                <p class="relative font-mono text-[11px] tracking-wider text-white/40">
                    &copy; {{ date('Y') }} وريد — جميع الحقوق محفوظة
                </p>
            </aside>

            <!-- Form column -->
            <main class="flex min-h-screen items-center justify-center bg-paper px-6 py-12">
                <div class="w-full max-w-md">
                    <a href="/" class="mb-8 flex items-center justify-center gap-2 lg:hidden">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-emerald text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5l1.2-3.6A8.25 8.25 0 1 1 7.5 21Z" />
                            </svg>
                        </span>
                        <span class="text-xl font-bold text-ink">وريد</span>
                    </a>

                    <div class="rounded-2xl border border-ink/10 bg-white p-8 shadow-luxe">
                        {{ $slot }}
                    </div>
                </div>
            </main>
        </div>
    </body>
</html>
