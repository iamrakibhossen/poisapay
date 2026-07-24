{{-- Settings › Preferences tab — editable spending priority order.
     Expects (from the settings view scope): $priorities, $coins. --}}
<x-settings.section :title="__('Spending priority')" :description="__('The order assets are used when spending (e.g. with cards).')">
    <form method="POST" action="{{ route('settings.preferences') }}" class="space-y-3"
        x-data="{
            items: @js($priorities),
            coins: @js($coins),
            adding: false,
            get available() { return this.coins.filter(c => ! this.items.some(i => i.assetId === c.assetId)); },
            add(assetId) { const c = this.coins.find(x => x.assetId === assetId); if (c) this.items.push(c); this.adding = false; },
            removeAt(i) { this.items.splice(i, 1); },
            up(i) { if (i > 0) { const t = this.items.splice(i, 1)[0]; this.items.splice(i - 1, 0, t); } },
            down(i) { if (i < this.items.length - 1) { const t = this.items.splice(i, 1)[0]; this.items.splice(i + 1, 0, t); } },
        }">
        @csrf
        @method('PUT')

        <div class="space-y-2.5">
            <template x-for="(item, i) in items" :key="item.assetId">
                <div class="flex items-center gap-3 rounded-xl border border-neutral-200 p-3">
                    <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-neutral-900 text-[11px] font-bold text-white" x-text="i + 1"></span>
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-neutral-100 text-[10px] font-semibold text-neutral-600" x-text="(item.symbol || '?').slice(0, 2)"></span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-neutral-900" x-text="item.symbol"></p>
                        <p class="truncate text-xs text-neutral-500" x-text="item.name"></p>
                    </div>
                    <span x-show="i === 0" x-cloak class="shrink-0 rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand-700">{{ __('Used first') }}</span>
                    <div class="flex shrink-0 items-center gap-0.5">
                        <button type="button" x-on:click="up(i)" :disabled="i === 0" class="grid h-7 w-7 place-items-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700 disabled:opacity-30 disabled:hover:bg-transparent" aria-label="{{ __('Move up') }}">
                            <x-heroicon-o-chevron-up class="h-4 w-4" />
                        </button>
                        <button type="button" x-on:click="down(i)" :disabled="i === items.length - 1" class="grid h-7 w-7 place-items-center rounded-lg text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-700 disabled:opacity-30 disabled:hover:bg-transparent" aria-label="{{ __('Move down') }}">
                            <x-heroicon-o-chevron-down class="h-4 w-4" />
                        </button>
                        <button type="button" x-on:click="removeAt(i)" class="grid h-7 w-7 place-items-center rounded-lg text-neutral-400 transition hover:bg-rose-50 hover:text-rose-600" aria-label="{{ __('Remove') }}">
                            <x-heroicon-o-x-mark class="h-4 w-4" />
                        </button>
                    </div>
                    <input type="hidden" name="order[]" :value="item.assetId" />
                </div>
            </template>

            <template x-if="items.length === 0">
                <div class="rounded-xl border border-dashed border-neutral-200 p-5 text-center text-sm text-neutral-500">
                    {{ __('No coins added yet. Add coins below to set the order they are spent in.') }}
                </div>
            </template>
        </div>

        {{-- Add coin --}}
        <div class="relative" x-show="available.length > 0" x-cloak>
            <button type="button" x-on:click="adding = ! adding"
                class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-700 transition hover:border-neutral-300 hover:bg-neutral-50">
                <x-heroicon-o-plus class="h-4 w-4" /> {{ __('Add coin') }}
            </button>
            <div x-show="adding" x-cloak x-on:click.outside="adding = false" x-transition.opacity
                class="absolute z-10 mt-1 max-h-64 w-64 overflow-auto rounded-xl border border-neutral-200 bg-white p-1 shadow-lg">
                <template x-for="c in available" :key="c.assetId">
                    <button type="button" x-on:click="add(c.assetId)" class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left transition hover:bg-neutral-50">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-neutral-100 text-[10px] font-semibold text-neutral-600" x-text="(c.symbol || '?').slice(0, 2)"></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium text-neutral-900" x-text="c.symbol"></span>
                            <span class="block truncate text-xs text-neutral-500" x-text="c.name"></span>
                        </span>
                    </button>
                </template>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-neutral-100 pt-3">
            <p class="text-xs text-neutral-400">{{ __('Coins are spent from the top down. Reorder with the arrows.') }}</p>
            <x-ui.button type="submit" size="sm">{{ __('Save order') }}</x-ui.button>
        </div>
    </form>
</x-settings.section>
