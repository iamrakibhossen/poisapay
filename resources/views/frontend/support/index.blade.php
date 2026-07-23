<x-layouts.app :title="__('Support')">
    @php
        $tabs = [
            'all' => __('All'),
            'open' => __('Open'),
            'pending' => __('Pending'),
            'resolved' => __('Resolved'),
            'closed' => __('Closed'),
        ];
        $catMeta = [
            'general' => ['icon' => 'chat-bubble-left-right', 'tint' => 'bg-brand-50 text-brand-600'],
            'account' => ['icon' => 'user-circle', 'tint' => 'bg-indigo-50 text-indigo-600'],
            'deposit' => ['icon' => 'arrow-down-tray', 'tint' => 'bg-green-50 text-green-600'],
            'withdrawal' => ['icon' => 'arrow-up-tray', 'tint' => 'bg-amber-50 text-amber-600'],
            'card' => ['icon' => 'credit-card', 'tint' => 'bg-sky-50 text-sky-600'],
            'kyc' => ['icon' => 'identification', 'tint' => 'bg-purple-50 text-purple-600'],
            'other' => ['icon' => 'ellipsis-horizontal-circle', 'tint' => 'bg-neutral-100 text-neutral-500'],
        ];
        $hasFilters = request()->filled('q') || $tab !== 'all';
    @endphp

    <div class="mx-auto max-w-4xl space-y-5">
        <x-ui.page-header :title="__('Support')" :subtitle="__('Get help from our team — we usually reply within a few hours.')">
            <x-slot:actions>
                <a href="{{ route('support.create') }}"><x-ui.button icon="plus">{{ __('New ticket') }}</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('status'))<x-ui.alert type="success">{{ session('status') }}</x-ui.alert>@endif

        {{-- Status tabs --}}
        <div class="flex gap-1 overflow-x-auto border-b border-neutral-200">
            @foreach ($tabs as $key => $label)
                @php $active = $tab === $key; @endphp
                <a href="{{ route('support.index', $key === 'all' ? [] : ['tab' => $key]) }}"
                   class="-mb-px flex items-center gap-2 whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition-colors {{ $active ? 'border-brand-500 text-neutral-900' : 'border-transparent text-neutral-500 hover:text-neutral-900' }}">
                    {{ $label }}
                    <span class="rounded-full px-1.5 py-0.5 text-xs tabular {{ $active ? 'bg-brand-50 text-brand-700' : 'bg-neutral-100 text-neutral-500' }}">{{ number_format($counts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </div>

        {{-- Search --}}
        <form method="GET" action="{{ route('support.index') }}" class="flex items-center gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="relative flex-1">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                <input type="text" name="q" value="{{ request('q') }}" placeholder="{{ __('Search tickets…') }}" class="pp-input h-10 w-full min-h-0 py-0 pl-9 text-sm">
            </div>
            <x-ui.button type="submit" variant="secondary" icon="magnifying-glass">{{ __('Search') }}</x-ui.button>
            @if ($hasFilters)
                <a href="{{ route('support.index') }}" class="text-sm font-medium text-neutral-500 hover:text-neutral-900">{{ __('Reset') }}</a>
            @endif
        </form>

        {{-- Tickets --}}
        @if ($tickets->isEmpty())
            <x-ui.card>
                <x-ui.empty-state icon="lifebuoy"
                    :title="$hasFilters ? __('No matching tickets') : __('No tickets yet')"
                    :description="$hasFilters ? __('Try a different tab or clear your search.') : __('Open a ticket and our team will help you out.')">
                    <x-slot:action>
                        @if ($hasFilters)
                            <a href="{{ route('support.index') }}"><x-ui.button variant="secondary">{{ __('Clear filters') }}</x-ui.button></a>
                        @else
                            <a href="{{ route('support.create') }}"><x-ui.button icon="plus">{{ __('New ticket') }}</x-ui.button></a>
                        @endif
                    </x-slot:action>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            <div class="divide-y divide-neutral-100 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-[var(--shadow-card)]">
                @foreach ($tickets as $ticket)
                    @php $m = $catMeta[$ticket->category] ?? $catMeta['other']; @endphp
                    <a href="{{ route('support.show', $ticket->id) }}" class="flex items-center gap-4 px-5 py-4 transition-colors hover:bg-neutral-50">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl {{ $m['tint'] }}">
                            <x-dynamic-component :component="'heroicon-o-'.$m['icon']" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <p class="truncate text-sm font-semibold text-neutral-900">{{ $ticket->subject }}</p>
                                @if ($ticket->priority === 'high')
                                    <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-red-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-red-600"><span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>{{ __('High') }}</span>
                                @endif
                            </div>
                            <p class="mt-0.5 truncate text-xs text-neutral-500">
                                {{ ucfirst($ticket->category) }}
                                <span class="text-neutral-300">·</span>
                                {{ $ticket->messages_count }} {{ $ticket->messages_count === 1 ? __('message') : __('messages') }}
                                <span class="text-neutral-300">·</span>
                                {{ __('Updated') }} {{ $ticket->updated_at->diffForHumans() }}
                            </p>
                        </div>
                        <x-ui.badge :color="$ticket->status->color()" dot>{{ $ticket->status->label() }}</x-ui.badge>
                        <x-heroicon-o-chevron-right class="hidden h-4 w-4 shrink-0 text-neutral-300 sm:block" />
                    </a>
                @endforeach
            </div>

            <div>{{ $tickets->links() }}</div>
        @endif
    </div>
</x-layouts.app>
