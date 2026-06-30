<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-lg font-bold text-ink">الفريق</h1>
            <p class="text-sm text-ink-soft">أضِف موظفي حسابك وأدِر صلاحياتهم.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <!-- Generic action errors (e.g. seat ceiling, can't remove owner/self) -->
        @if ($errors->has('team'))
            <div class="rounded-xl border border-[#B5462F]/30 bg-[#B5462F]/5 px-4 py-3 text-sm font-medium text-[#B5462F]">
                {{ $errors->first('team') }}
            </div>
        @endif

        <!-- Seat counter -->
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-ink/10 bg-white p-5 shadow-luxe">
            <div>
                <p class="font-mono text-[11px] uppercase tracking-wider text-ink-soft">المقاعد المستخدمة</p>
                <p class="mt-1 text-2xl font-bold text-ink tabular-nums">
                    {{ $seatsUsed }} <span class="text-ink-soft">/ {{ $maxUsers }}</span>
                </p>
            </div>
            @if (! $canAddUser)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-gold/30 bg-gold/10 px-3 py-1.5 text-xs font-semibold text-ink-2">
                    <svg class="h-4 w-4 text-gold" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    بلغت حدّ المقاعد
                </span>
            @endif
        </div>

        <!-- Members list -->
        <x-card title="أعضاء الفريق" subtitle="المالك والموظفون ضمن حسابك.">
            <ul class="divide-y divide-ink/10">
                @foreach ($members as $member)
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3.5">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-emerald/10 font-semibold text-emerald">
                                {{ mb_substr($member->name, 0, 1) }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-ink">{{ $member->name }}</p>
                                <p class="truncate font-mono text-xs text-ink-soft" dir="ltr">{{ $member->email }}</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-3">
                            @if ($member->isOwner())
                                <span class="inline-flex items-center rounded-full border border-emerald/30 bg-emerald/10 px-2.5 py-0.5 text-xs font-semibold text-emerald-deep">مالك</span>
                            @else
                                <span class="inline-flex items-center rounded-full border border-ink/10 bg-paper/60 px-2.5 py-0.5 text-xs font-semibold text-ink-2">موظف</span>
                            @endif

                            {{-- Remove is shown only for agents (never the owner); the controller also enforces this (§13). --}}
                            @if (! $member->isOwner() && $member->id !== auth()->id())
                                <form method="POST" action="{{ route('team.destroy', $member) }}" onsubmit="return confirm('هل أنت متأكد من إزالة هذا الموظف؟');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="grid h-9 w-9 place-items-center rounded-lg border border-ink/10 text-[#B5462F] transition hover:bg-[#B5462F]/5" aria-label="إزالة">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <!-- Add a team member -->
        <x-card title="إضافة موظف" subtitle="ينضمّ الموظف بصلاحية «موظف» ويستطيع الدخول مباشرة.">
            @if ($canAddUser)
                <form method="POST" action="{{ route('team.store') }}" class="space-y-5">
                    @csrf

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="name" :value="'الاسم'" />
                            <x-text-input id="name" name="name" type="text" class="mt-1.5 block w-full" :value="old('name')" required autocomplete="off" />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="email" :value="'البريد الإلكتروني'" />
                            <x-text-input id="email" name="email" type="email" dir="ltr" class="mt-1.5 block w-full font-mono" :value="old('email')" required autocomplete="off" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="password" :value="'كلمة المرور'" />
                            <x-text-input id="password" name="password" type="password" dir="ltr" class="mt-1.5 block w-full font-mono" required autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="password_confirmation" :value="'تأكيد كلمة المرور'" />
                            <x-text-input id="password_confirmation" name="password_confirmation" type="password" dir="ltr" class="mt-1.5 block w-full font-mono" required autocomplete="new-password" />
                            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-ink/10 pt-5">
                        <x-primary-button>إضافة الموظف</x-primary-button>
                    </div>
                </form>
            @else
                <div class="rounded-xl border border-dashed border-ink/15 bg-paper/50 p-6 text-center">
                    <p class="text-sm font-medium text-ink-2">بلغت حدّ المقاعد المتاح ({{ $maxUsers }}).</p>
                    <p class="mt-1 text-xs text-ink-soft">لإضافة المزيد من الموظفين تواصل مع الدعم لرفع عدد المقاعد.</p>
                </div>
            @endif
        </x-card>
    </div>
</x-app-layout>
