@php
    // Command-palette menu search. Reads the shared menu tree, filtered to the
    // destinations this operator can actually reach, and hands a flat list to Alpine.
    $admin = auth('admin')->user();
    $can = fn ($perm) => empty($perm) || $admin?->can($perm) || $admin?->hasRole('super-admin');

    $searchItems = collect(\App\Support\AdminMenu::searchItems())
        ->filter(fn ($i) => $can($i['perm'] ?? null))
        ->map(fn ($i) => [
            'label' => $i['label'],
            'group' => $i['group'] ?? '',
            'href' => ! empty($i['url']) ? $i['url'] : ($i['route'] ? route($i['route']) : '#'),
            'target' => $i['target'] ?? null,
        ])
        ->values();
@endphp

<div
    x-data="{
        open: false,
        query: '',
        active: 0,
        items: @js($searchItems),
        get results() {
            const q = this.query.trim().toLowerCase();
            const list = ! q
                ? this.items
                : this.items.filter(i => i.label.toLowerCase().includes(q) || (i.group || '').toLowerCase().includes(q));
            if (this.active >= list.length) this.active = Math.max(0, list.length - 1);
            return list;
        },
        openPalette() {
            this.open = true;
            this.query = '';
            this.active = 0;
            this.$nextTick(() => this.$refs.search?.focus());
        },
        move(delta) {
            const n = this.results.length;
            if (n) this.active = (this.active + delta + n) % n;
        },
        go() {
            const r = this.results[this.active];
            if (! r) return;
            this.open = false;
            if (r.target) { window.open(r.href, r.target); return; }
            window.location = r.href;
        },
    }"
    @keydown.window.meta.k.prevent="openPalette()"
    @keydown.window.ctrl.k.prevent="openPalette()"
    class="min-w-0 flex-1"
>
    {{-- Trigger --}}
    <button type="button" @click="openPalette()"
        class="flex w-full max-w-xs items-center gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500 transition hover:border-gray-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-blue-200">
        <x-heroicon-o-magnifying-glass class="h-4 w-4 shrink-0" />
        <span class="flex-1 text-left">{{ __('Search menu…') }}</span>
        <kbd class="hidden shrink-0 rounded border border-gray-300 bg-white px-1.5 font-sans text-[11px] font-medium text-gray-400 sm:inline">⌘K</kbd>
    </button>

    {{-- Palette --}}
    <div x-show="open" x-cloak class="fixed inset-0 z-[60]" @keydown.escape.window="open = false">
        <div class="fixed inset-0 bg-gray-900/40" @click="open = false"></div>
        <div class="relative mx-auto mt-[10vh] w-full max-w-lg px-4">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-2xl"
                @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="go()">
                <div class="flex items-center gap-3 border-b border-gray-100 px-4">
                    <x-heroicon-o-magnifying-glass class="h-5 w-5 shrink-0 text-gray-400" />
                    <input x-ref="search" x-model="query" type="text" autocomplete="off" spellcheck="false"
                        placeholder="{{ __('Jump to…') }}"
                        class="w-full border-0 bg-transparent py-3.5 text-sm text-gray-800 placeholder-gray-400 focus:outline-none focus:ring-0">
                    <kbd class="hidden shrink-0 rounded border border-gray-300 bg-gray-50 px-1.5 py-0.5 font-sans text-[11px] text-gray-400 sm:inline">esc</kbd>
                </div>

                <ul class="max-h-80 overflow-y-auto py-1" role="listbox">
                    <template x-for="(item, i) in results" :key="item.href + i">
                        <li>
                            <a :href="item.href" :target="item.target || '_self'"
                                @mouseenter="active = i" @click="open = false"
                                class="flex items-center gap-3 px-4 py-2.5 text-sm"
                                :class="active === i ? 'bg-blue-50 text-blue-700' : 'text-gray-700'">
                                <span class="min-w-0 flex-1 truncate" x-text="item.label"></span>
                                <span class="shrink-0 text-xs" :class="active === i ? 'text-blue-400' : 'text-gray-400'" x-text="item.group"></span>
                            </a>
                        </li>
                    </template>
                    <li x-show="results.length === 0" class="px-4 py-10 text-center text-sm text-gray-500">
                        {{ __('No matching pages') }}
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>
