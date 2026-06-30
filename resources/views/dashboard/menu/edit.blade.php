<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">قائمة الخدمات</h1>
            <p class="text-sm text-ink-soft">جهّز قائمة تفاعلية يختار منها عميلك خدمةً فيحصل على ردّ جاهز أو يُحوَّل لموظف.</p>
        </div>
    </x-slot>

    @if (session('status') === 'menu-updated')
        <div class="mb-6 rounded-xl border border-emerald/30 bg-emerald/10 px-4 py-3 text-sm font-medium text-emerald">
            تم حفظ قائمة الخدمات بنجاح.
        </div>
    @endif

    @php
        // Seed the Alpine editor from old() (on a validation error) or the stored
        // rows. Every value is JSON-encoded server-side so it is safely escaped in
        // the attribute (§13). row_key is intentionally NOT exposed — it is
        // regenerated server-side on save.
        $seedRows = collect(old('rows', $menu->rows->map(fn ($row) => [
            'title' => $row->title,
            'description' => $row->description,
            'action_type' => $row->action_type,
            'reply_text' => $row->reply_text,
        ])->all()))->values();
    @endphp

    <form
        method="POST"
        action="{{ route('menu.update') }}"
        class="space-y-6"
        x-data="serviceMenuEditor({
            enabled: {{ old('enabled', $menu->enabled) ? 'true' : 'false' }},
            triggerOnWelcome: {{ old('trigger_on_welcome', $menu->trigger_on_welcome) ? 'true' : 'false' }},
            rows: {{ Js::from($seedRows) }}
        })"
    >
        @csrf
        @method('PUT')

        <!-- Toggles -->
        <x-card title="تفعيل القائمة" subtitle="عند التفعيل يعرض البوت هذه القائمة عندما يطلبها العميل (أو في أول رسالة إن فعّلت الترحيب).">
            <div class="space-y-4">
                <label class="flex items-start justify-between gap-4">
                    <span>
                        <span class="block text-sm font-semibold text-ink">تفعيل قائمة الخدمات</span>
                        <span class="block text-xs text-ink-soft">عند الإيقاف يتجاهل البوت القائمة ويرد بالذكاء الاصطناعي كالمعتاد.</span>
                    </span>
                    <input type="hidden" name="enabled" :value="enabled ? 1 : 0">
                    <button type="button" @click="enabled = !enabled"
                        :class="enabled ? 'bg-emerald' : 'bg-ink/20'"
                        class="relative mt-1 inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition">
                        <span :class="enabled ? '-translate-x-5' : 'translate-x-0'"
                            class="inline-block h-5 w-5 translate-y-0.5 -translate-x-0.5 transform rounded-full bg-white shadow transition"></span>
                    </button>
                </label>

                <label class="flex items-start justify-between gap-4 border-t border-ink/10 pt-4">
                    <span>
                        <span class="block text-sm font-semibold text-ink">الترحيب بالقائمة تلقائياً</span>
                        <span class="block text-xs text-ink-soft">إرسال القائمة في أول رسالة من العميل دون أن يطلبها.</span>
                    </span>
                    <input type="hidden" name="trigger_on_welcome" :value="triggerOnWelcome ? 1 : 0">
                    <button type="button" @click="triggerOnWelcome = !triggerOnWelcome"
                        :class="triggerOnWelcome ? 'bg-emerald' : 'bg-ink/20'"
                        class="relative mt-1 inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full transition">
                        <span :class="triggerOnWelcome ? '-translate-x-5' : 'translate-x-0'"
                            class="inline-block h-5 w-5 translate-y-0.5 -translate-x-0.5 transform rounded-full bg-white shadow transition"></span>
                    </button>
                </label>
            </div>
        </x-card>

        <!-- Menu text -->
        <x-card title="نصوص القائمة" subtitle="هذه النصوص تظهر للعميل في رسالة القائمة التفاعلية.">
            <div class="space-y-5">
                <div>
                    <x-input-label for="header" :value="'الرأس (اختياري · حتى 60 حرفاً)'" />
                    <input id="header" name="header" type="text" maxlength="60"
                        value="{{ old('header', $menu->header) }}"
                        class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm focus:border-emerald focus:ring-emerald/30"
                        placeholder="مثال: مرحباً بك في متجرنا">
                    <x-input-error :messages="$errors->get('header')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="body" :value="'نص القائمة (مطلوب · حتى 1024 حرفاً)'" />
                    <textarea id="body" name="body" rows="3" required maxlength="1024"
                        class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm leading-relaxed text-ink shadow-sm focus:border-emerald focus:ring-emerald/30"
                        placeholder="كيف يمكننا خدمتك؟ اختر من القائمة بالأسفل.">{{ old('body', $menu->body) }}</textarea>
                    <x-input-error :messages="$errors->get('body')" class="mt-2" />
                </div>

                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="button_label" :value="'نص الزر (مطلوب · حتى 20 حرفاً)'" />
                        <input id="button_label" name="button_label" type="text" required maxlength="20"
                            value="{{ old('button_label', $menu->button_label) }}"
                            class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm focus:border-emerald focus:ring-emerald/30"
                            placeholder="الخدمات">
                        <x-input-error :messages="$errors->get('button_label')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="footer" :value="'التذييل (اختياري · حتى 60 حرفاً)'" />
                        <input id="footer" name="footer" type="text" maxlength="60"
                            value="{{ old('footer', $menu->footer) }}"
                            class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm focus:border-emerald focus:ring-emerald/30"
                            placeholder="مثال: نسعد بخدمتك">
                        <x-input-error :messages="$errors->get('footer')" class="mt-2" />
                    </div>
                </div>
            </div>
        </x-card>

        <!-- Rows editor -->
        <x-card title="صفوف القائمة" subtitle="حتى 10 خيارات. كل خيار إمّا ردّ جاهز أو تحويل لموظف.">
            <x-input-error :messages="$errors->get('rows')" class="mb-4" />

            <div class="space-y-4">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="rounded-2xl border border-ink/10 bg-paper/60 p-5">
                        <div class="mb-4 flex items-center justify-between">
                            <span class="rounded-lg bg-ink/5 px-2.5 py-1 text-xs font-bold text-ink-soft"
                                x-text="'خيار ' + (index + 1)"></span>
                            <button type="button" @click="removeRow(index)"
                                class="rounded-lg px-2.5 py-1 text-xs font-medium text-rose-600 transition hover:bg-rose-50">
                                حذف
                            </button>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="text-xs font-medium text-ink-soft">العنوان (حتى 24 حرفاً)</label>
                                <input type="text" maxlength="24" required
                                    :name="`rows[${index}][title]`"
                                    x-model="row.title"
                                    class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm focus:border-emerald focus:ring-emerald/30">
                            </div>
                            <div>
                                <label class="text-xs font-medium text-ink-soft">الوصف (اختياري · حتى 72 حرفاً)</label>
                                <input type="text" maxlength="72"
                                    :name="`rows[${index}][description]`"
                                    x-model="row.description"
                                    class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm text-ink shadow-sm focus:border-emerald focus:ring-emerald/30">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="text-xs font-medium text-ink-soft">الإجراء عند الاختيار</label>
                            <div class="mt-2 flex flex-wrap gap-3">
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-ink/15 bg-white px-3 py-2 text-sm"
                                    :class="row.action_type === 'reply' ? 'ring-2 ring-emerald/40 border-emerald' : ''">
                                    <input type="radio" value="reply" x-model="row.action_type"
                                        :name="`rows[${index}][action_type]`" class="text-emerald focus:ring-emerald/30">
                                    <span class="font-medium text-ink">ردّ جاهز</span>
                                </label>
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-ink/15 bg-white px-3 py-2 text-sm"
                                    :class="row.action_type === 'handoff' ? 'ring-2 ring-amber-400 border-amber-400' : ''">
                                    <input type="radio" value="handoff" x-model="row.action_type"
                                        :name="`rows[${index}][action_type]`" class="text-amber-500 focus:ring-amber-300">
                                    <span class="font-medium text-ink">تحويل لموظف</span>
                                </label>
                            </div>
                        </div>

                        <div class="mt-4" x-show="row.action_type === 'reply'" x-cloak>
                            <label class="text-xs font-medium text-ink-soft">نص الرد الجاهز</label>
                            <textarea rows="3" maxlength="1024"
                                :name="`rows[${index}][reply_text]`"
                                x-model="row.reply_text"
                                class="mt-1.5 block w-full rounded-xl border-ink/15 bg-white text-sm leading-relaxed text-ink shadow-sm focus:border-emerald focus:ring-emerald/30"
                                placeholder="النص الذي يُرسَل للعميل عند اختيار هذا الخيار."></textarea>
                        </div>
                    </div>
                </template>

                <p x-show="rows.length === 0" class="rounded-xl border border-dashed border-ink/15 bg-paper/40 px-4 py-6 text-center text-sm text-ink-soft">
                    لا توجد صفوف بعد. أضف خياراً واحداً على الأقل لتفعيل القائمة.
                </p>

                <button type="button" @click="addRow()" x-show="rows.length < 10"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-emerald/40 bg-emerald/5 px-4 py-3 text-sm font-semibold text-emerald transition hover:bg-emerald/10">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    إضافة خيار
                    <span class="text-xs font-normal text-emerald/70" x-text="`(${rows.length}/10)`"></span>
                </button>
            </div>
        </x-card>

        <div class="flex justify-end">
            <x-primary-button>حفظ قائمة الخدمات</x-primary-button>
        </div>
    </form>

    <script>
        function serviceMenuEditor(initial) {
            return {
                enabled: initial.enabled,
                triggerOnWelcome: initial.triggerOnWelcome,
                rows: (initial.rows || []).map(r => ({
                    title: r.title ?? '',
                    description: r.description ?? '',
                    action_type: r.action_type ?? 'reply',
                    reply_text: r.reply_text ?? '',
                })),
                addRow() {
                    if (this.rows.length >= 10) return;
                    this.rows.push({ title: '', description: '', action_type: 'reply', reply_text: '' });
                },
                removeRow(index) {
                    this.rows.splice(index, 1);
                },
            };
        }
    </script>
</x-app-layout>
