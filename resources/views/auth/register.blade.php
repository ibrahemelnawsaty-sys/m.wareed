<x-guest-layout>
    <div class="mb-7 text-center">
        <h1 class="text-2xl font-bold text-ink">أنشئ حساب عملك</h1>
        <p class="mt-2 text-sm text-ink-soft">ابدأ مجاناً — دقائق وتربط بوتك الذكي على واتساب.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="space-y-5">
        @csrf

        <!-- Business name -->
        <div>
            <x-input-label for="business_name" :value="'اسم العمل / النشاط'" />
            <x-text-input id="business_name" class="mt-1.5 block w-full" type="text" name="business_name" :value="old('business_name')" required autofocus autocomplete="organization" placeholder="مثال: متجر وريد" />
            <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
        </div>

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="'اسمك'" />
            <x-text-input id="name" class="mt-1.5 block w-full" type="text" name="name" :value="old('name')" required autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email -->
        <div>
            <x-input-label for="email" :value="'البريد الإلكتروني'" />
            <x-text-input id="email" class="mt-1.5 block w-full" type="email" name="email" :value="old('email')" required autocomplete="username" dir="ltr" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Password -->
        <div>
            <x-input-label for="password" :value="'كلمة المرور'" />
            <x-text-input id="password" class="mt-1.5 block w-full" type="password" name="password" required autocomplete="new-password" dir="ltr" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm password -->
        <div>
            <x-input-label for="password_confirmation" :value="'تأكيد كلمة المرور'" />
            <x-text-input id="password_confirmation" class="mt-1.5 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" dir="ltr" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <x-primary-button class="w-full justify-center">
            إنشاء الحساب
        </x-primary-button>

        <p class="text-center text-sm text-ink-soft">
            لديك حساب بالفعل؟
            <a class="font-semibold text-emerald transition hover:text-emerald-deep" href="{{ route('login') }}">
                تسجيل الدخول
            </a>
        </p>
    </form>
</x-guest-layout>
