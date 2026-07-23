<x-layouts.app :title="__('Notifications')">
    @php
        // Filter chips shown above the feed: All + Unread, then one per category.
        $chips = [
            ['key' => 'all', 'label' => __('All'), 'count' => $total],
            ['key' => 'unread', 'label' => __('Unread'), 'count' => $unreadCount],
        ];
        foreach ($categoryMeta as $key => $meta) {
            $chips[] = ['key' => $key, 'label' => $meta['label'], 'count' => $categoryCounts[$key] ?? 0];
        }
    @endphp

    <div class="mx-auto max-w-3xl space-y-6">
        <x-ui.page-header :title="__('Notifications')" :subtitle="__('Your recent account activity.')">
            <x-slot:actions>
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm" icon="check">{{ __('Mark all as read') }}</x-ui.button>
                    </form>
                @endif
                <x-ui.button href="{{ route('notifications.preferences') }}" variant="ghost" size="sm" icon="adjustments-horizontal">{{ __('Preferences') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Filter chips (plain GET query param) --}}
        <div class="-mx-1 flex flex-nowrap gap-2 overflow-x-auto px-1 pb-1">
            @foreach ($chips as $chip)
                <a href="{{ route('notifications.index', ['filter' => $chip['key']]) }}"
                    class="pp-chip shrink-0 {{ $filter === $chip['key'] ? 'is-on' : '' }}">
                    {{ $chip['label'] }}
                    @if ($chip['count'] > 0)
                        <span class="inline-flex h-4 min-w-4 items-center justify-center rounded-full px-1 text-[11px] font-bold {{ $filter === $chip['key'] ? 'bg-brand-500 text-white' : 'bg-neutral-100 text-neutral-500' }}">{{ $chip['count'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>

        @if ($groups->isEmpty())
            <x-ui.card>
                @if ($total === 0)
                    <x-ui.empty-state icon="bell" :title="__('No notifications yet')"
                        :description="__('Account activity, security alerts and product updates will show up here.')" />
                @else
                    <x-ui.empty-state icon="funnel" :title="__('Nothing to show')"
                        :description="__('No notifications match this filter.')">
                        <x-slot:action>
                            <x-ui.button href="{{ route('notifications.index') }}" variant="secondary" size="sm">{{ __('View all') }}</x-ui.button>
                        </x-slot:action>
                    </x-ui.empty-state>
                @endif
            </x-ui.card>
        @else
            @foreach ($groups as $bucket => $notes)
                <div>
                    <h2 class="mb-2 px-1 text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ $bucket }}</h2>
                    <div class="divide-y divide-neutral-100 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-[var(--shadow-card)]">
                        @foreach ($notes as $note)
                            @php $meta = $categoryMeta[$note['category']]; @endphp
                            {{-- The whole row is a form: clicking marks the notification read
                                 (clearing it from the unread count) then follows its deep link. --}}
                            <form method="POST" action="{{ route('notifications.read', $note['id']) }}" class="block">
                                @csrf
                                {{-- The button holds only phrasing content (spans/svg) so the whole
                                     row is one reliable click target. --}}
                                <button type="submit"
                                    class="flex w-full items-center gap-3.5 px-4 py-4 text-left transition {{ $note['is_unread'] ? 'bg-brand-50/60 hover:bg-brand-50' : 'hover:bg-neutral-50/70' }}">
                                    {{-- Category icon --}}
                                    <span class="relative grid h-10 w-10 shrink-0 place-items-center rounded-full {{ $meta['tint'] }}">
                                        <x-dynamic-component :component="'heroicon-o-'.$meta['icon']" class="h-5 w-5" />
                                        @if ($note['is_unread'])
                                            <span class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-brand-500 ring-2 ring-white"></span>
                                        @endif
                                    </span>

                                    <span class="block min-w-0 flex-1">
                                        <span class="block font-semibold {{ $note['is_unread'] ? 'text-neutral-900' : 'text-neutral-700' }}">{{ $note['title'] }}</span>

                                        @if ($note['body'])
                                            <span class="mt-0.5 block text-sm text-neutral-600">{{ $note['body'] }}</span>
                                        @endif

                                        <span class="mt-2 flex flex-wrap items-center gap-2 text-xs text-neutral-400">
                                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-medium {{ $meta['tint'] }}">{{ $meta['label'] }}</span>
                                            <span>{{ $note['created'] }}</span>
                                        </span>
                                    </span>

                                    @if ($note['url'])
                                        <x-heroicon-o-chevron-right class="h-5 w-5 shrink-0 text-neutral-300" />
                                    @endif
                                </button>
                            </form>
                        @endforeach
                    </div>
                </div>
            @endforeach

            {{-- Pagination --}}
            @if ($paginator->hasPages())
                <div class="flex items-center justify-between text-sm">
                    @if ($paginator->onFirstPage())
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-100 px-3 py-1.5 font-medium text-neutral-300">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
                        </span>
                    @else
                        <a href="{{ $paginator->previousPageUrl() }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50">
                            <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Previous') }}
                        </a>
                    @endif
                    <span class="text-neutral-500">{{ __('Page :current of :last', ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span>
                    @if ($paginator->hasMorePages())
                        <a href="{{ $paginator->nextPageUrl() }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 bg-white px-3 py-1.5 font-medium text-neutral-700 transition hover:bg-neutral-50">
                            {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-100 px-3 py-1.5 font-medium text-neutral-300">
                            {{ __('Next') }} <x-heroicon-o-chevron-right class="h-4 w-4" />
                        </span>
                    @endif
                </div>
            @endif
        @endif
    </div>
</x-layouts.app>
