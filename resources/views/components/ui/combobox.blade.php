@props([
    'name' => null,
    'label' => null,
    'options' => [],       // [['value'=>, 'label'=>, 'icon'?=>, 'group'?=>], ...]
    'value' => null,       // selected value
    'placeholder' => __('Select…'),
    'searchable' => true,
    'clearable' => true,
    'error' => null,
    'hint' => null,
])

@php
    // Normalise options so every item has value/label/icon/group keys.
    $opts = collect($options)->map(fn ($o) => [
        'value' => (string) ($o['value'] ?? $o['id'] ?? ''),
        'label' => (string) ($o['label'] ?? $o['name'] ?? $o['value'] ?? ''),
        'icon' => $o['icon'] ?? null,
        'group' => $o['group'] ?? null,
    ])->values();
    $fieldId = $name ? $name.'-combobox' : null;
@endphp

<div {{ $attributes->only('class') }}>
    @if ($label)
        <label class="pp-label" @if ($fieldId) for="{{ $fieldId }}" @endif>{{ $label }}</label>
    @endif

    <div
        x-data="{
            open: false,
            query: '',
            value: @js((string) $value),
            options: @js($opts),
            active: 0,
            get filtered() {
                if (! this.query.trim()) return this.options;
                const q = this.query.toLowerCase();
                return this.options.filter(o => o.label.toLowerCase().includes(q));
            },
            get selected() { return this.options.find(o => o.value === this.value) ?? null; },
            toggle() {
                this.open = ! this.open;
                if (this.open) this.$nextTick(() => {
                    this.$refs.search?.focus();
                    this.active = Math.max(0, this.filtered.findIndex(o => o.value === this.value));
                });
            },
            choose(o) { this.value = o.value; this.open = false; this.query = ''; },
            clear() { this.value = ''; this.query = ''; },
            move(d) { const n = this.filtered.length; if (n) this.active = (this.active + d + n) % n; },
            pick() { const o = this.filtered[this.active]; if (o) this.choose(o); },
            groupHeader(i) { const o = this.filtered[i]; return o.group && (i === 0 || this.filtered[i - 1].group !== o.group); },
        }"
        x-on:keydown.escape="open = false"
        @click.outside="open = false"
        class="relative"
    >
        <input type="hidden" @if ($name) name="{{ $name }}" @endif :value="value" />

        {{-- Control --}}
        <button type="button" id="{{ $fieldId }}" x-on:click="toggle()"
            @class([
                'flex min-h-[2.625rem] w-full items-center gap-2 rounded-lg border bg-white px-3.5 text-left text-sm transition',
                'border-red-400 focus:ring-2 focus:ring-red-500/20' => $error,
                'border-gray-300 hover:border-brand-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20' => ! $error,
            ])
            :class="open && 'border-brand-500 ring-2 ring-brand-500/20'">
            <span class="flex-1 truncate" :class="selected ? 'font-medium text-gray-800' : 'text-gray-400'"
                x-text="selected ? selected.label : '{{ $placeholder }}'"></span>
            @if ($clearable)
                <span x-show="value" x-cloak x-on:click.stop="clear()" class="rounded p-0.5 text-gray-400 hover:text-gray-600" role="button" aria-label="{{ __('Clear') }}">
                    <x-heroicon-o-x-mark class="h-4 w-4" />
                </span>
            @endif
            <x-heroicon-o-chevron-up-down class="h-4 w-4 shrink-0 text-gray-400" />
        </button>

        {{-- Panel --}}
        <div x-show="open" x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
            class="absolute z-30 mt-1.5 w-full overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg ring-1 ring-slate-900/5">
            @if ($searchable)
                <div class="border-b border-gray-100 p-2">
                    <div class="flex items-center gap-2 rounded-lg bg-gray-50 px-2.5">
                        <x-heroicon-o-magnifying-glass class="h-4 w-4 shrink-0 text-gray-400" />
                        <input x-ref="search" x-model="query" type="text" placeholder="{{ __('Search…') }}"
                            x-on:keydown.arrow-down.prevent="move(1)" x-on:keydown.arrow-up.prevent="move(-1)"
                            x-on:keydown.enter.prevent="pick()"
                            class="w-full border-0 bg-transparent py-2 text-sm focus:outline-none focus:ring-0" />
                    </div>
                </div>
            @endif
            <div class="max-h-60 overflow-y-auto p-1">
                <template x-for="(o, i) in filtered" :key="o.value">
                    <div>
                        <p x-show="groupHeader(i)" class="px-3 pb-1 pt-2 text-[11px] font-semibold uppercase tracking-wide text-gray-400" x-text="o.group"></p>
                        <button type="button" x-on:click="choose(o)" x-on:mouseenter="active = i"
                            class="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-left text-sm transition"
                            :class="i === active ? 'bg-brand-50 text-brand-700' : 'text-gray-700 hover:bg-gray-50'">
                            <span class="flex-1 truncate" x-text="o.label"></span>
                            <x-heroicon-o-check class="h-4 w-4 text-brand-600" x-show="o.value === value" />
                        </button>
                    </div>
                </template>
                <p x-show="! filtered.length" class="px-3 py-6 text-center text-sm text-gray-400">{{ __('No matches.') }}</p>
            </div>
        </div>
    </div>

    @if ($error)
        <p class="mt-1.5 text-xs text-red-600">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-xs text-gray-500">{{ $hint }}</p>
    @endif
</div>
