<x-layouts.app :title="__('Inbox')">
    <div class="mt-6 space-y-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Inbox') }}</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ __('Order conversations with your buyers.') }}</p>
        </div>

        @if (count($threads))
            <div class="divide-y divide-neutral-100 overflow-hidden rounded-2xl border border-neutral-200 bg-white shadow-[var(--shadow-card)]">
                @foreach ($threads as $t)
                    <a href="{{ route('shop.order', ['id' => $t['id']]) }}" class="flex items-start gap-3 px-4 py-3.5 transition hover:bg-neutral-50">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">{{ $t['initials'] }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-2">
                                <p class="truncate text-sm font-semibold text-neutral-900">{{ $t['buyer'] }}</p>
                                <span class="shrink-0 text-[11px] text-neutral-400">{{ $t['time'] }}</span>
                            </div>
                            <p class="truncate text-xs text-neutral-500">{{ $t['product'] }} · <span class="font-mono">{{ $t['number'] }}</span></p>
                            <p class="truncate text-xs text-neutral-400">{{ $t['preview'] }}</p>
                        </div>
                        @if ($t['unread'])
                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-500" title="{{ __('Unread') }}"></span>
                        @endif
                    </a>
                @endforeach
            </div>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="chat-bubble-left-right" :title="__('No messages yet')" :description="__('When a buyer messages you about an order, the conversation shows up here.')" />
            </div>
        @endif
    </div>
</x-layouts.app>
