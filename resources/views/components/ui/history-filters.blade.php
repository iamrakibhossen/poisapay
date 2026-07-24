@props([
    'route',                       // route name, e.g. 'deposit.history'
    'tabParam' => 'status',        // query key the tabs write to
    'tabs' => [],                  // [key => label]
    'active' => 'all',             // current tab value
    'symbols' => [],               // asset symbols for the dropdown
    'asset' => 'all',              // current asset value
    'search' => '',                // current search value
    'searchPlaceholder' => null,
])

{{-- Filter toolbar shared by the history pages: pill tabs + asset dropdown +
     search. Tabs are plain query-string links; the form carries the active tab
     in a hidden field so filters compose. --}}
<div class="flex flex-col gap-3 lg:flex-row lg:items-center">
    <div class="-mx-1 flex flex-nowrap gap-1 overflow-x-auto px-1 lg:flex-wrap">
        @foreach ($tabs as $key => $label)
            <a href="{{ route($route, array_merge(request()->query(), [$tabParam => $key, 'page' => 1])) }}"
                class="shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium transition {{ $active === $key ? 'bg-neutral-900 text-white' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-800' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route($route) }}" class="flex gap-2 lg:ml-auto">
        <input type="hidden" name="{{ $tabParam }}" value="{{ $active }}" />
        <select name="asset" onchange="this.form.submit()" class="pp-input w-32 text-sm">
            <option value="all">{{ __('All assets') }}</option>
            @foreach ($symbols as $symbol)
                <option value="{{ $symbol }}" @selected($asset === $symbol)>{{ $symbol }}</option>
            @endforeach
        </select>
        <div class="relative flex-1 lg:w-56 lg:flex-none">
            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
            <input type="search" name="search" value="{{ $search }}" placeholder="{{ $searchPlaceholder ?? __('Search…') }}" class="pp-input w-full !pl-10 text-sm" />
        </div>
    </form>
</div>
