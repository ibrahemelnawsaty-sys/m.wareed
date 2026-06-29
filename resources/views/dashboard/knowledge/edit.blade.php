<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">تعديل المستند</h1>
            <p class="text-sm text-ink-soft">حدّث محتوى هذا المستند في قاعدة المعرفة.</p>
        </div>
    </x-slot>

    <x-card>
        {{-- Delete is its own form (HTML forms cannot nest). The update form
             references it via the `form` attribute on the submit button. --}}
        <form id="delete-document" method="POST" action="{{ route('knowledge.destroy', $document) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا المستند؟');">
            @csrf
            @method('DELETE')
        </form>

        <form method="POST" action="{{ route('knowledge.update', $document) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="title" :value="'العنوان'" />
                <x-text-input id="title" name="title" type="text" class="mt-1.5 block w-full" :value="old('title', $document->title)" required autofocus />
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
                >{{ old('content', $document->content) }}</textarea>
                <x-input-error :messages="$errors->get('content')" class="mt-2" />
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-ink/10 pt-5">
                <x-danger-button type="submit" form="delete-document">حذف المستند</x-danger-button>
                <div class="flex items-center gap-3">
                    <a href="{{ route('knowledge.index') }}" class="inline-flex items-center rounded-xl border border-ink/15 bg-white px-5 py-2.5 text-sm font-semibold text-ink-2 transition hover:bg-paper">إلغاء</a>
                    <x-primary-button>حفظ التغييرات</x-primary-button>
                </div>
            </div>
        </form>
    </x-card>
</x-app-layout>
