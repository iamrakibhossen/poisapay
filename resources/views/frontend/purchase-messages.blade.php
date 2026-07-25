<x-layouts.app :title="__('Messages')">
    <div class="mx-auto mt-6 max-w-2xl space-y-5">
        {{-- Header --}}
        <div>
            <a href="{{ route('purchases') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900">
                <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('My purchases') }}
            </a>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-semibold tracking-tight text-neutral-900">{{ __('Message') }} {{ $seller }}</h1>
                <span class="font-mono text-xs text-neutral-400">{{ $order->number }}</span>
            </div>
        </div>

        <x-ui.card>
            <div class="mb-3 flex items-center justify-between">
                <p class="text-sm font-semibold text-neutral-900">{{ __('Conversation') }}</p>
                <span class="inline-flex items-center gap-1 text-[11px] text-neutral-400"><x-heroicon-o-users class="h-3.5 w-3.5" /> {{ __('You & the seller') }}</span>
            </div>

            <div class="max-h-[52vh] space-y-3 overflow-y-auto rounded-xl bg-neutral-50/50 p-3">
                @forelse ($messages as $m)
                    <div class="flex {{ $m['side'] === 'mine' ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[78%]">
                            <p class="mb-0.5 text-[11px] text-neutral-400 {{ $m['side'] === 'mine' ? 'text-right' : '' }}">{{ $m['author'] }}</p>
                            <div class="rounded-2xl px-3.5 py-2 text-sm {{ $m['side'] === 'mine' ? 'rounded-br-sm bg-brand-500 text-white' : 'rounded-bl-sm border border-neutral-200 bg-white text-neutral-800' }}">{{ $m['body'] }}</div>
                            <p class="mt-0.5 text-[11px] text-neutral-300 {{ $m['side'] === 'mine' ? 'text-right' : '' }}">{{ $m['at'] }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-8 text-center text-xs text-neutral-400">{{ __('No messages yet. Ask the seller anything about your order.') }}</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('purchases.messages.send', ['order' => $order->id]) }}" class="mt-3 flex items-end gap-2">
                @csrf
                <textarea name="body" rows="1" required placeholder="{{ __('Write a message…') }}"
                    class="max-h-28 min-h-[42px] flex-1 resize-none rounded-xl border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">{{ old('body') }}</textarea>
                <x-ui.button type="submit" icon="paper-airplane">{{ __('Send') }}</x-ui.button>
            </form>
            @error('body')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </x-ui.card>
    </div>
</x-layouts.app>
