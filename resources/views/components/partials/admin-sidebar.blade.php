@php
    // DollarHub admin sidebar — dark navy (#002044) with #2C4DD4 active.
    // Flat grouped nav (section headings + direct links); a FEW items carry
    // `children` and expand into a submenu (auto-open when a child is current).
    // Menu tree lives in App\Support\AdminMenu (shared with the topbar search).
    $groups = \App\Support\AdminMenu::groups();

    $admin = auth('admin')->user();
    $can = fn ($perm) => empty($perm) || $admin?->can($perm) || $admin?->hasRole('super-admin');
@endphp

{{-- Mobile backdrop --}}
<div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false" x-transition.opacity
    class="fixed inset-0 z-30 bg-black/50 lg:hidden"></div>

<div x-show="sidebarOpen" x-cloak
    class="fixed lg:static inset-y-0 left-0 z-40 h-full w-[256px] shrink-0 overflow-hidden text-sm text-gray-100 bg-[#002044]">
    <div class="relative h-full overflow-y-auto">
        <div class="px-3 pb-8">

            {{-- Logo --}}
            <div class="mb-2 flex h-16 items-center justify-center">
                <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 text-lg font-bold uppercase tracking-wider">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-500 text-ink-900">
                        <x-heroicon-s-bolt class="h-5 w-5" />
                    </span>
                    <span class="text-white">PoisaPay</span>
                </a>
            </div>

            <nav class="flex flex-col">
                @foreach ($groups as $group)
                    @php
                        // Keep only items the operator can see (a parent survives if any child is visible).
                        $items = collect($group['items'])->filter(function ($item) use ($can) {
                            return isset($item['children'])
                                ? collect($item['children'])->contains(fn ($c) => $can($c['perm'] ?? null))
                                : $can($item['perm'] ?? null);
                        })->all();
                    @endphp
                    @continue(empty($items))

                    @if (! empty($group['heading']))
                        <p class="mb-1 mt-4 px-3 text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $group['heading'] }}</p>
                    @endif

                    @foreach ($items as $item)
                        {{-- Direct link (named route, or an external URL e.g. Horizon) --}}
                        @if (empty($item['children']))
                            @php
                                $href = ! empty($item['url']) ? $item['url'] : route($item['route']);
                                $active = ! empty($item['route']) && request()->routeIs($item['route']);
                            @endphp
                            <a href="{{ $href }}" @if (! empty($item['target'])) target="{{ $item['target'] }}" rel="noopener" @endif
                                @class([
                                    'mb-0.5 flex items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition',
                                    'bg-[#2C4DD4] text-white' => $active,
                                    'text-[#B2C1CC] hover:bg-white/5 hover:text-white' => ! $active,
                                ])>
                                <x-dynamic-component :component="$item['icon']" class="h-5 w-5 shrink-0" />
                                <span class="flex-1">{{ $item['label'] }}</span>
                                @if (! empty($item['target']))<x-heroicon-o-arrow-top-right-on-square class="h-4 w-4 shrink-0 opacity-60" />@endif
                            </a>
                        {{-- Item with a submenu --}}
                        @else
                            @php
                                $children = collect($item['children'])->filter(fn ($c) => $can($c['perm'] ?? null))->all();
                                $open = collect($children)->contains(fn ($c) => request()->routeIs($c['route']));
                            @endphp
                            <div x-data="{ open: @js($open) }" class="mb-0.5">
                                <button type="button" @click="open = ! open"
                                    @class([
                                        'flex w-full items-center gap-3 rounded-lg px-3 py-2.5 font-semibold transition',
                                        'text-white' => $open,
                                        'text-[#B2C1CC] hover:bg-white/5 hover:text-white' => ! $open,
                                    ])>
                                    <x-dynamic-component :component="$item['icon']" class="h-5 w-5 shrink-0" />
                                    <span class="flex-1 text-left">{{ $item['label'] }}</span>
                                    <x-heroicon-o-chevron-right class="h-4 w-4 shrink-0 transition-transform duration-200" x-bind:class="open ? 'rotate-90' : ''" />
                                </button>
                                <div x-show="open"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                                    x-cloak class="mt-0.5 ps-4">
                                    <div class="space-y-0.5 border-s border-white/10 ps-2">
                                        @foreach ($children as $child)
                                            @php $active = request()->routeIs($child['route']); @endphp
                                            <a href="{{ route($child['route']) }}"
                                                @class([
                                                    'flex items-center rounded-lg px-3 py-2 text-sm transition',
                                                    'bg-[#2C4DD4] font-semibold text-white' => $active,
                                                    'text-[#9fb0bd] hover:bg-white/5 hover:text-white' => ! $active,
                                                ])>
                                                <span class="me-2.5 h-1.5 w-1.5 shrink-0 rounded-full {{ $active ? 'bg-brand-500' : 'bg-white/25' }}"></span>
                                                <span>{{ $child['label'] }}</span>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                @endforeach
            </nav>
        </div>
    </div>
</div>
