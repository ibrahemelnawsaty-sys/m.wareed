<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl bg-emerald px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition duration-150 ease-in-out hover:bg-emerald-deep focus:outline-none focus:ring-2 focus:ring-emerald/40 focus:ring-offset-2 active:scale-[.99] disabled:opacity-50']) }}>
    {{ $slot }}
</button>
