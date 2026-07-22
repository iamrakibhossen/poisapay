<x-layouts.admin :title="'Notifications'">
    @php
        $meta = fn (string $c) => match ($c) {
            'deposit' => ['icon' => 'arrow-down-tray', 'bg' => 'bg-emerald-50', 'text' => 'text-emerald-600'],
            'withdrawal' => ['icon' => 'arrow-up-tray', 'bg' => 'bg-amber-50', 'text' => 'text-amber-600'],
            'kyc' => ['icon' => 'identification', 'bg' => 'bg-sky-50', 'text' => 'text-sky-600'],
            'invoice' => ['icon' => 'receipt-percent', 'bg' => 'bg-brand-50', 'text' => 'text-brand-600'],
            default => ['icon' => 'bell', 'bg' => 'bg-neutral-100', 'text' => 'text-neutral-500'],
        };
    @endphp
    <div class="space-y-6">
        <x-ui.page-header title="Notifications" subtitle="Operator alerts across the platform.">
            <x-slot:actions>
                @if ($unread > 0)
                    <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm" icon="check">Mark all read ({{ $unread }})</x-ui.button>
                    </form>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card>
            @forelse ($notifications as $n)
                @php $c = $meta($n->data['category'] ?? 'general'); @endphp
                <div class="flex items-start gap-3 py-3 {{ ! $loop->last ? 'border-b border-neutral-100' : '' }} {{ is_null($n->read_at) ? '-mx-2 rounded-lg bg-brand-50/40 px-2' : '' }}">
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full {{ $c['bg'] }} {{ $c['text'] }}">
                        <x-dynamic-component :component="'heroicon-o-'.$c['icon']" class="h-4 w-4" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-neutral-900">{{ $n->data['title'] ?? 'Notification' }}</p>
                        <p class="text-xs text-neutral-500">{{ $n->data['body'] ?? '' }}</p>
                        <p class="mt-0.5 text-[11px] text-neutral-400">{{ $n->created_at->diffForHumans() }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if (! empty($n->data['url']))
                            <a href="{{ $n->data['url'] }}" class="text-xs font-semibold text-amber-700 hover:text-amber-800">View</a>
                        @endif
                        @if (is_null($n->read_at))
                            <form method="POST" action="{{ route('admin.notifications.read', $n->id) }}">
                                @csrf
                                <button type="submit" class="text-xs text-neutral-400 hover:text-neutral-700">Mark read</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="bell-slash" title="No notifications" description="Operator alerts will appear here." />
            @endforelse
        </x-ui.card>

        {{ $notifications->links() }}
    </div>
</x-layouts.admin>
