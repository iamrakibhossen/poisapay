@props([
    'title' => '',
    'section' => 'overview',
    'period' => null,
    'presets' => [],
    'filters' => [],
    'nav' => [],
    'compare' => true,
])

@php
    $action = $section === 'overview'
        ? route('admin.analytics')
        : route('admin.analytics.section', $section);

    $query = array_filter([
        'period' => $filters['period'] ?? null,
        'from' => $filters['from'] ?? null,
        'to' => $filters['to'] ?? null,
        'compare' => $compare ? 1 : 0,
    ], fn ($v) => $v !== null && $v !== '');

    $exportUrl = ($section === 'overview'
        ? route('admin.analytics.export')
        : route('admin.analytics.export', $section)).'?'.http_build_query($query);
@endphp

<div
    x-data="{
        period: '{{ $filters['period'] ?? 'last_30_days' }}',
        presets: @js(array_keys($presets)),
        pick(i) {
            const opts = this.$refs.period?.options;
            if (opts && opts[i]) {
                this.period = opts[i].value;
                this.$refs.period.value = opts[i].value;
                if (opts[i].value !== 'custom') this.$refs.form.submit();
            }
        },
        kbd(e) {
            const t = e.target;
            if (t && (t.tagName === 'INPUT' || t.tagName === 'SELECT' || t.tagName === 'TEXTAREA' || t.isContentEditable)) return;
            if (e.metaKey || e.ctrlKey || e.altKey) return;
            if (e.key >= '1' && e.key <= '9') this.pick(parseInt(e.key, 10) - 1);
            else if (e.key === '0') this.pick(9);
            else if (e.key === 'c') { this.$refs.compare.checked = ! this.$refs.compare.checked; this.$refs.form.submit(); }
            else if (e.key === 'e') this.$refs.exportLink.click();
        }
    }"
    @keydown.window="kbd($event)"
    class="sticky top-[3.25rem] z-20 -mx-4 -mt-4 border-b border-neutral-200 bg-gray-100/85 px-4 pt-4 pb-3 backdrop-blur sm:-mx-5 sm:px-5 lg:-mx-6 lg:px-6 print:static print:mx-0 print:mt-0 print:border-0 print:bg-transparent print:px-0 print:pt-0"
>
    {{-- Header row --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="truncate text-lg font-semibold text-neutral-900">{{ $title }}</h1>
            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-neutral-500">
                <span class="inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                {{ __('Showing') }} <span class="font-medium text-neutral-700">{{ $period?->label }}</span>
                @if ($compare)<span class="text-neutral-400">· {{ __('vs previous') }}</span>@endif
            </p>
        </div>

        <div class="flex items-center gap-2">
            {{-- Period selector --}}
            <form method="GET" action="{{ $action }}" x-ref="form" class="flex items-center gap-2">
                <div class="relative">
                    <select name="period" x-ref="period" x-model="period"
                            @change="period !== 'custom' && $refs.form.submit()"
                            class="appearance-none rounded-lg border-neutral-200 bg-white py-2 pl-3 pr-9 text-sm font-medium text-neutral-800 shadow-sm focus:border-brand-400 focus:ring-brand-400">
                        @foreach ($presets as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['period'] ?? 'last_30_days') === $key)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                    <x-heroicon-m-chevron-down class="pointer-events-none absolute right-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                </div>

                <template x-if="period === 'custom'">
                    <div class="flex items-center gap-1.5">
                        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                               class="rounded-lg border-neutral-200 py-2 text-sm text-neutral-800 shadow-sm focus:border-brand-400 focus:ring-brand-400">
                        <span class="text-neutral-400">–</span>
                        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                               class="rounded-lg border-neutral-200 py-2 text-sm text-neutral-800 shadow-sm focus:border-brand-400 focus:ring-brand-400">
                        <button type="submit" class="rounded-lg bg-[#002044] px-3 py-2 text-sm font-semibold text-white transition hover:bg-[#00306a]">{{ __('Apply') }}</button>
                    </div>
                </template>

                {{-- Compare toggle (auto-applies) --}}
                <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-2.5 py-2 text-xs font-semibold text-neutral-600 shadow-sm transition hover:border-neutral-300"
                       title="{{ __('Compare to previous period') }} (c)">
                    <input type="hidden" name="compare" value="0">
                    <input type="checkbox" name="compare" value="1" x-ref="compare" @change="$refs.form.submit()" @checked($compare)
                           class="h-3.5 w-3.5 rounded border-neutral-300 text-brand-600 focus:ring-brand-400">
                    {{ __('Compare') }}
                </label>
            </form>

            <div class="mx-0.5 h-6 w-px bg-neutral-200"></div>

            <a href="{{ $exportUrl }}" x-ref="exportLink" title="{{ __('Export CSV') }} (e)"
               class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-2.5 py-2 text-xs font-semibold text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-50">
                <x-heroicon-o-arrow-down-tray class="h-4 w-4" /> <span class="hidden sm:inline">{{ __('Export') }}</span>
            </a>
            <button type="button" onclick="window.print()" title="{{ __('Print report') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-2.5 py-2 text-xs font-semibold text-neutral-700 shadow-sm transition hover:border-neutral-300 hover:bg-neutral-50">
                <x-heroicon-o-printer class="h-4 w-4" />
            </button>
        </div>
    </div>

    {{-- Section tabs (segmented) --}}
    <div class="mt-3 flex gap-1 overflow-x-auto rounded-xl bg-neutral-200/60 p-1 print:hidden">
        @foreach ($nav as $group => $items)
            @foreach ($items as $item)
                <a href="{{ $item['url'] }}" @class([
                    'inline-flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                    'bg-white text-[#002044] shadow-sm' => $item['active'],
                    'text-neutral-500 hover:text-neutral-800' => ! $item['active'],
                ])>
                    <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="h-4 w-4" />
                    {{ $item['title'] }}
                </a>
            @endforeach
        @endforeach
    </div>
</div>
