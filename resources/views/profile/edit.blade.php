<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">الملف الشخصي</h1>
            <p class="text-sm text-ink-soft">إدارة بيانات حسابك وكلمة المرور.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <x-card>
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </x-card>

        <x-card>
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </x-card>

        <x-card>
            <div class="max-w-xl">
                @include('profile.partials.delete-user-form')
            </div>
        </x-card>
    </div>
</x-app-layout>
