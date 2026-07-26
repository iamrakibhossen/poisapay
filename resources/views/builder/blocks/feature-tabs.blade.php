@php $tabs = $props['items'] ?? []; @endphp
@if (! empty($tabs))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14" x-data="{ tab: 0 }">
        <div class="mx-auto max-w-4xl px-5">
            @if (! empty($props['heading']))<h2 class="text-center text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] }}</h2>@endif
            <div class="mt-7 flex flex-wrap justify-center gap-2">
                @foreach ($tabs as $i => $t)
                    <button type="button" x-on:click="tab = {{ $i }}" :class="tab === {{ $i }} ? 'text-white' : 'text-neutral-600 hover:bg-neutral-100'" :style="tab === {{ $i }} ? 'background: var(--pp-accent); border-color: transparent' : ''" class="rounded-full border border-neutral-200 px-4 py-2 text-sm font-semibold transition">{{ is_array($t) ? ($t['title'] ?? '') : $t }}</button>
                @endforeach
            </div>
            @foreach ($tabs as $i => $t)
                @php $t = is_array($t) ? $t : ['title' => $t]; @endphp
                <div x-show="tab === {{ $i }}" x-cloak x-transition.opacity class="mt-7 rounded-3xl border border-neutral-200 bg-white p-6 sm:p-8">
                    <div class="grid items-center gap-6 sm:grid-cols-2">
                        <div>
                            <h3 class="text-lg font-bold text-neutral-900">{{ $t['title'] ?? '' }}</h3>
                            @if (! empty($t['body']))<p class="mt-2 text-sm leading-relaxed text-neutral-500">{{ $t['body'] }}</p>@endif
                        </div>
                        @if (! empty($t['image']))
                            <x-builder.image :src="$t['image']" :alt="$t['title'] ?? ''" sizes="(min-width: 768px) 50vw, 100vw" class="w-full rounded-2xl object-cover" />
                        @else
                            <div class="grid h-40 place-items-center rounded-2xl bg-neutral-50 text-neutral-300"><x-heroicon-o-photo class="h-8 w-8" /></div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>
@endif
