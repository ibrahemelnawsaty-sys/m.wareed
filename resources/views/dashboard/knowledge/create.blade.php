<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">مستند جديد</h1>
            <p class="text-sm text-ink-soft">أضِف معلومة جديدة إلى قاعدة معرفة بوتك.</p>
        </div>
    </x-slot>

    <x-card>
        <form method="POST" action="{{ route('knowledge.store') }}" class="space-y-5">
            @csrf

            <div>
                <x-input-label for="title" :value="'العنوان'" />
                <x-text-input id="title" name="title" type="text" class="mt-1.5 block w-full" :value="old('title')" required autofocus placeholder="مثال: سياسة الإرجاع والاستبدال" />
                <x-input-error :messages="$errors->get('title')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="content" :value="'المحتوى'" />
                <textarea
                    id="content"
                    name="content"
                    rows="10"
                    required
                    class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm leading-relaxed text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-emerald focus:ring-emerald/30"
                    placeholder="اكتب المعلومة كما تريد أن يعتمد عليها البوت في الرد..."
                >{{ old('content') }}</textarea>
                <x-input-error :messages="$errors->get('content')" class="mt-2" />
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-ink/10 pt-5">
                <a href="{{ route('knowledge.index') }}" class="inline-flex items-center rounded-xl border border-ink/15 bg-white px-5 py-2.5 text-sm font-semibold text-ink-2 transition hover:bg-paper">إلغاء</a>
                <x-primary-button>حفظ المستند</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
