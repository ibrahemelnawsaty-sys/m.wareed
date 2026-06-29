<x-guest-layout>
    <div class="mb-7 text-center">
        <h1 class="text-2xl font-bold text-ink">مرحباً بعودتك</h1>
        <p class="mt-2 text-sm text-ink-soft">سجّل الدخول لإدارة بوت واتساب الخاص بك.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Email -->
        <div>
            <x-input-label for="email" :value="'البريد الإلكتروني'" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" dir="ltr" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="'كلمة المرور'" />
            <x-text-input id="password" class="mt-1.5 block w-full" type="password" name="password" required autocomplete="current-password" dir="ltr" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-ink/20 text-emerald shadow-sm focus:ring-emerald/40" name="remember">
                <span class="ms-2 text-sm text-ink-soft">تذكّرني</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm font-medium text-emerald transition hover:text-emerald-deep" href="{{ route('password.request') }}">
                    نسيت كلمة المرور؟
                </a>
            @endif
        </div>

        <x-primary-button class="w-full justify-center">
            تسجيل الدخول
        </x-primary-button>

        <p class="text-center text-sm text-ink-soft">
            ليس لديك حساب؟
            <a class="font-semibold text-emerald transition hover:text-emerald-deep" href="{{ route('register') }}">
                أنشئ حساباً جديداً
            </a>
        </p>
    </form>
</x-guest-layout>
