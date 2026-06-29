<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">ربط واتساب</h1>
            <p class="text-sm text-ink-soft">اربط رقم عملك عبر WhatsApp Cloud API الرسمي.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Read-only integration values -->
        <x-card title="إعدادات الـ Webhook" subtitle="انسخ هذه القيم والصقها في إعدادات تطبيقك على منصة Meta للمطوّرين.">
            <div class="space-y-5">
                <x-copy-field label="Callback URL" :value="$callbackUrl" />
                <x-copy-field label="Verify token" :value="$verifyToken" />

                <div class="flex items-start gap-3 rounded-xl border border-gold/30 bg-gold/10 px-4 py-3 text-sm text-ink-2">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-gold" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <p>
                        قيمة <span class="font-mono font-semibold">App Secret</span> تُضبط في ملف
                        <span class="font-mono font-semibold">.env</span> على الخادم فقط، ولا تُدار من هذه اللوحة لأسباب أمنية.
                    </p>
                </div>
            </div>
        </x-card>

        <!-- Editable connection form -->
        <x-card title="بيانات الرقم" subtitle="املأ بيانات الرقم والتوكن كما تظهر في لوحة WhatsApp Cloud API.">
            <form method="POST" action="{{ route('whatsapp.update') }}" class="space-y-5">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="display_name" :value="'الاسم الظاهر'" />
                    <x-text-input id="display_name" name="display_name" type="text" class="mt-1.5 block w-full" :value="old('display_name', $account->display_name)" placeholder="اسم العمل كما يظهر للعملاء" />
                    <x-input-error :messages="$errors->get('display_name')" class="mt-2" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="phone_number_id" :value="'Phone Number ID'" />
                        <x-text-input id="phone_number_id" name="phone_number_id" type="text" dir="ltr" class="mt-1.5 block w-full font-mono" :value="old('phone_number_id', $account->phone_number_id)" placeholder="123456789012345" />
                        <x-input-error :messages="$errors->get('phone_number_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="waba_id" :value="'WABA ID'" />
                        <x-text-input id="waba_id" name="waba_id" type="text" dir="ltr" class="mt-1.5 block w-full font-mono" :value="old('waba_id', $account->waba_id)" placeholder="123456789012345" />
                        <x-input-error :messages="$errors->get('waba_id')" class="mt-2" />
                    </div>
                </div>

                <div>
                    <x-input-label for="access_token" :value="'Access Token'" />
                    <x-text-input
                        id="access_token"
                        name="access_token"
                        type="password"
                        dir="ltr"
                        autocomplete="off"
                        class="mt-1.5 block w-full font-mono"
                        :placeholder="$hasAccessToken ? '••••••••••  محفوظ' : 'الصق التوكن هنا'"
                    />
                    <x-input-error :messages="$errors->get('access_token')" class="mt-2" />
                    <p class="mt-2 text-xs text-ink-soft">
                        @if ($hasAccessToken)
                            توكن محفوظ ومشفّر بالفعل. اتركه فارغاً للإبقاء عليه، أو أدخل توكناً جديداً لاستبداله.
                        @else
                            يُخزَّن التوكن مشفّراً ولا يُعرض مرة أخرى بعد الحفظ.
                        @endif
                    </p>
                </div>

                <div class="flex justify-end border-t border-ink/10 pt-5">
                    <x-primary-button>حفظ بيانات الربط</x-primary-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
