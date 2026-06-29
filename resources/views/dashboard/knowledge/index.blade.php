<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">قاعدة المعرفة</h1>
            <p class="text-sm text-ink-soft">المعلومات التي يعتمد عليها بوتك في الردود.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <p class="text-sm text-ink-soft">
                <span class="font-bold text-ink">{{ $documents->count() }}</span> مستند
            </p>
            <a href="{{ route('knowledge.create') }}" class="inline-flex items-center gap-2 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                مستند جديد
            </a>
        </div>

        @if ($documents->isEmpty())
            <div class="rounded-2xl border border-dashed border-ink/15 bg-white p-12 text-center shadow-sm">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald/10 text-emerald">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" /></svg>
                </span>
                <h3 class="mt-4 text-base font-bold text-ink">لا توجد مستندات بعد</h3>
                <p class="mx-auto mt-1 max-w-sm text-sm text-ink-soft">أضف معلومات عن منتجاتك وخدماتك وأسعارك ليجيب بوتك بدقة.</p>
                <a href="{{ route('knowledge.create') }}" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                    إضافة أول مستند
                </a>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($documents as $document)
                    <div class="flex items-center gap-4 rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe transition hover:border-emerald/30">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-emerald/10 text-emerald">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate font-bold text-ink">{{ $document->title }}</h3>
                            <p class="mt-0.5 truncate text-sm text-ink-soft">{{ \Illuminate\Support\Str::limit($document->content, 90) }}</p>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <a href="{{ route('knowledge.edit', $document) }}" class="grid h-9 w-9 place-items-center rounded-lg border border-ink/10 text-ink-2 transition hover:bg-paper" aria-label="تعديل">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z" /></svg>
                            </a>
                            <form method="POST" action="{{ route('knowledge.destroy', $document) }}" onsubmit="return confirm('هل أنت متأكد من حذف هذا المستند؟');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="grid h-9 w-9 place-items-center rounded-lg border border-ink/10 text-[#B5462F] transition hover:bg-[#B5462F]/5" aria-label="حذف">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-app-layout>
