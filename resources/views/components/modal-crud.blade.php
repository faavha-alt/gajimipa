@props([
    'show',        // Nama properti Livewire yang di-entangle, mis. 'showModal'
    'title' => null,
    'maxWidth' => 'md',
])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '3xl' => 'sm:max-w-3xl',
][$maxWidth];
@endphp

<div
    x-data="{
        show: window.Livewire.entangle('{{ $show }}'),
        focusables() {
            let selector = 'a, button, input:not([type=\'hidden\']), textarea, select, details, [tabindex]:not([tabindex=\'-1\'])'
            return [...$el.querySelectorAll(selector)].filter(el => ! el.hasAttribute('disabled'))
        },
        firstFocusable() { return this.focusables()[0] },
        lastFocusable() { return this.focusables().slice(-1)[0] },
        nextFocusable() { return this.focusables()[this.nextFocusableIndex()] || this.firstFocusable() },
        prevFocusable() { return this.focusables()[this.prevFocusableIndex()] || this.lastFocusable() },
        nextFocusableIndex() { return (this.focusables().indexOf(document.activeElement) + 1) % (this.focusables().length + 1) },
        prevFocusableIndex() { return Math.max(0, this.focusables().indexOf(document.activeElement)) - 1 },
        restoreFocusEl: null,
    }"
    x-init="
        $watch('show', value => {
            if (value) {
                restoreFocusEl = document.activeElement;
                document.body.classList.add('overflow-y-hidden');
                $nextTick(() => setTimeout(() => this.firstFocusable()?.focus(), 50));
            } else {
                document.body.classList.remove('overflow-y-hidden');
                restoreFocusEl?.focus?.();
                restoreFocusEl = null;
            }
        })
    "
    x-on:keydown.escape.window="show = false"
    x-show="show"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto px-4 py-6 sm:px-0"
>
    {{-- Backdrop --}}
    <div
        x-show="show"
        class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm"
        x-on:click="show = false"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
    ></div>

    {{-- Panel --}}
    <div
        x-show="show"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="$title ? 'modal-title' : null"
        x-on:keydown.tab.prevent="$event.shiftKey ? prevFocusable().focus() : nextFocusable().focus()"
        x-on:keydown.shift.tab.prevent="prevFocusable().focus()"
        x-transition:enter="ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave="ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
        x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
        class="relative my-auto w-full {{ $maxWidth }} overflow-hidden rounded-2xl bg-white shadow-xl ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800"
    >
        @if ($title)
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                <h2 id="modal-title" class="text-base font-semibold text-slate-900 dark:text-white">{{ $title }}</h2>
                <button type="button" x-on:click="show = false" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-slate-200">
                    <span class="sr-only">Tutup</span>
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>
        @endif

        {{ $slot }}
    </div>
</div>
