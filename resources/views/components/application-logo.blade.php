@props(['withText' => false])

<div {{ $attributes->merge(['class' => 'inline-flex items-center gap-2.5']) }}>
    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 text-sm font-bold text-white shadow-sm shadow-indigo-500/30">
        M
    </span>

    @if ($withText)
        <span class="leading-tight">
            <span class="block text-sm font-bold tracking-tight text-slate-900 dark:text-white">Gaji MIPA</span>
            <span class="block text-[11px] font-medium text-slate-400 dark:text-slate-500">Fakultas MIPA UNS</span>
        </span>
    @endif
</div>
