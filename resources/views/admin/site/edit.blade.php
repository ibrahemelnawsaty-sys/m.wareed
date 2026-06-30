{{--
    Public site content — super-admin only (Phase 4h). UNLIKE the AI keys page,
    these are PUBLIC marketing copy, so each field renders its live stored value
    for editing. Every value is escaped with `{{ }}` here and on the landing page
    (§13, no HTML injection). A blank field reverts that field to its hard-coded
    landing default (§3). CSRF + FormRequest validated.
--}}
<x-admin-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">محتوى الموقع</h1>
            <p class="text-sm text-ink-soft">حرّر نصوص الصفحة الرئيسية وبيانات تحسين محركات البحث (SEO).</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        {{-- Validation errors --}}
        @if ($errors->any())
            <div class="rounded-xl border border-[#B5462F]/30 bg-[#B5462F]/5 px-4 py-3 text-sm font-medium text-[#B5462F]">
                <ul class="space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Helper note --}}
        <div class="rounded-2xl border border-gold/30 bg-gold/5 px-5 py-4 text-sm text-ink-2">
            <div class="flex items-start gap-3">
                <svg class="mt-0.5 h-5 w-5 shrink-0 text-gold" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                </svg>
                <div class="space-y-1">
                    <p class="font-semibold text-ink">كيف يعمل التحرير</p>
                    <p>اترك أي حقل فارغاً ليعود إلى نصّه الافتراضي في الصفحة الرئيسية — لا شيء ينكسر.</p>
                    <p class="text-ink-soft">عنوان SEO الأمثل ≤ 60 حرفاً، والوصف ≤ 160 حرفاً ليظهرا كاملين في نتائج جوجل.</p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.site.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- ===== Brand & contact ===== --}}
            <x-card title="العلامة والتواصل" subtitle="اسم العلامة وبيانات التواصل المعروضة في الموقع.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="brand_name" value="اسم العلامة" />
                        <x-text-input id="brand_name" name="brand_name" type="text" class="mt-2 block w-full"
                            :value="old('brand_name', $values['brand_name'])" placeholder="وريد" maxlength="120" />
                    </div>
                    <div>
                        <x-input-label for="contact_email" value="البريد الإلكتروني" />
                        <x-text-input id="contact_email" name="contact_email" type="email" dir="ltr" class="mt-2 block w-full text-start"
                            :value="old('contact_email', $values['contact_email'])" placeholder="info@m.wareed.vip" maxlength="255" />
                    </div>
                    <div>
                        <x-input-label for="contact_phone" value="رقم الهاتف" />
                        <x-text-input id="contact_phone" name="contact_phone" type="text" dir="ltr" class="mt-2 block w-full text-start"
                            :value="old('contact_phone', $values['contact_phone'])" placeholder="+966 ..." maxlength="50" />
                    </div>
                    <div>
                        <x-input-label for="contact_address" value="العنوان" />
                        <x-text-input id="contact_address" name="contact_address" type="text" class="mt-2 block w-full"
                            :value="old('contact_address', $values['contact_address'])" maxlength="500" />
                    </div>
                </div>
            </x-card>

            {{-- ===== Hero ===== --}}
            <x-card title="القسم الرئيسي (الهيرو)" subtitle="النصوص الكبيرة في أعلى الصفحة الرئيسية.">
                <div class="space-y-5">
                    <div>
                        <x-input-label for="hero_eyebrow" value="السطر التمهيدي (الشارة)" />
                        <x-text-input id="hero_eyebrow" name="hero_eyebrow" type="text" class="mt-2 block w-full"
                            :value="old('hero_eyebrow', $values['hero_eyebrow'])" maxlength="255" />
                    </div>
                    <div>
                        <x-input-label for="hero_title" value="العنوان الرئيسي" />
                        <x-text-input id="hero_title" name="hero_title" type="text" class="mt-2 block w-full"
                            :value="old('hero_title', $values['hero_title'])" maxlength="255" />
                    </div>
                    <div>
                        <x-input-label for="hero_subtitle" value="الوصف تحت العنوان" />
                        <textarea id="hero_subtitle" name="hero_subtitle" rows="3" maxlength="1000"
                            class="mt-2 block w-full rounded-xl border-ink/15 bg-white text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-emerald focus:ring-emerald/30">{{ old('hero_subtitle', $values['hero_subtitle']) }}</textarea>
                    </div>
                    <div>
                        <x-input-label for="hero_cta" value="نص زر الدعوة للتسجيل" />
                        <x-text-input id="hero_cta" name="hero_cta" type="text" class="mt-2 block w-full"
                            :value="old('hero_cta', $values['hero_cta'])" maxlength="120" />
                    </div>
                </div>
            </x-card>

            {{-- ===== SEO ===== --}}
            <x-card title="تحسين محركات البحث (SEO)" subtitle="ما يظهر في عنوان المتصفّح ونتائج جوجل ومشاركات التواصل.">
                <div class="space-y-5">
                    <div>
                        <x-input-label for="seo_title" value="عنوان الصفحة (Title)" />
                        <x-text-input id="seo_title" name="seo_title" type="text" class="mt-2 block w-full"
                            :value="old('seo_title', $values['seo_title'])" maxlength="60" />
                        <p class="mt-1.5 text-xs text-ink-soft">الأمثل ≤ 60 حرفاً.</p>
                    </div>
                    <div>
                        <x-input-label for="seo_description" value="وصف الصفحة (Description)" />
                        <textarea id="seo_description" name="seo_description" rows="2" maxlength="160"
                            class="mt-2 block w-full rounded-xl border-ink/15 bg-white text-ink shadow-sm transition placeholder:text-ink-soft/60 focus:border-emerald focus:ring-emerald/30">{{ old('seo_description', $values['seo_description']) }}</textarea>
                        <p class="mt-1.5 text-xs text-ink-soft">الأمثل ≤ 160 حرفاً.</p>
                    </div>
                    <div>
                        <x-input-label for="seo_keywords" value="الكلمات المفتاحية" />
                        <x-text-input id="seo_keywords" name="seo_keywords" type="text" class="mt-2 block w-full"
                            :value="old('seo_keywords', $values['seo_keywords'])" placeholder="بوت واتساب, رد آلي, ..." maxlength="500" />
                    </div>
                </div>
            </x-card>

            {{-- ===== Announcement ===== --}}
            <x-card title="شريط الإعلان العلوي" subtitle="يظهر شريط أعلى الصفحة الرئيسية إن كتبت نصاً هنا — اتركه فارغاً لإخفائه.">
                <div>
                    <x-input-label for="announcement" value="نص الإعلان (اختياري)" />
                    <x-text-input id="announcement" name="announcement" type="text" class="mt-2 block w-full"
                        :value="old('announcement', $values['announcement'])" maxlength="500" placeholder="مثال: عرض الإطلاق — شهر مجاني لأول 100 عميل" />
                </div>
            </x-card>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition hover:bg-emerald-deep">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" /></svg>
                    حفظ المحتوى
                </button>
            </div>
        </form>
    </div>
</x-admin-layout>
