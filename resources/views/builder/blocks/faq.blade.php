@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
        <div class="mx-auto max-w-2xl px-5" x-data="{ open: 0 }">
            <p class="text-center text-xs font-semibold uppercase tracking-wider" style="color: var(--pp-accent)">{{ __('FAQ') }}</p>
            <h2 class="mt-2 text-center text-2xl font-bold tracking-tight">{{ $props['heading'] ?? __('Questions & answers') }}</h2>
            <div class="mt-8 space-y-3">
                @foreach ($items as $i => $q)
                    <div class="overflow-hidden rounded-2xl border border-neutral-200 bg-white">
                        <button type="button" x-on:click="open = (open === {{ $i }} ? null : {{ $i }})" class="flex w-full items-center justify-between gap-3 px-5 py-4 text-left">
                            <span class="text-sm font-semibold text-neutral-800">{{ is_array($q) ? ($q['q'] ?? '') : $q }}</span>
                            <x-heroicon-o-chevron-down class="h-4 w-4 shrink-0 text-neutral-400 transition" x-bind:class="open === {{ $i }} && 'rotate-180'" />
                        </button>
                        @if (is_array($q) && ! empty($q['a']))
                            <div x-show="open === {{ $i }}" x-cloak
                                x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
                                <p class="px-5 pb-4 text-sm leading-relaxed text-neutral-500">{{ $q['a'] }}</p>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endif
