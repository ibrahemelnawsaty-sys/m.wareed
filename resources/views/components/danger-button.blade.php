<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center gap-2 rounded-xl border border-transparent bg-[#B5462F] px-5 py-2.5 text-sm font-semibold text-white shadow-luxe transition duration-150 ease-in-out hover:bg-[#9c3a26] focus:outline-none focus:ring-2 focus:ring-[#B5462F]/40 focus:ring-offset-2 active:scale-[.99]']) }}>
    {{ $slot }}
</button>
