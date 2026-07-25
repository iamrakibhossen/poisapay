<x-layouts.admin :title="__('Refund request')">
    @php
        $asset = $request->order?->asset;
        $money = fn ($v) => $asset ? $asset->money((string) (int) $v)->format(2) : (string) $v;
        $open = $request->status->isOpen();
    @endphp

    <div class="mx-auto max-w-3xl space-y-6">
        <a href="{{ route('admin.sell-refunds') }}" class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-900">
            <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Refund review') }}
        </a>

        <x-ui.card>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-lg font-semibold text-gray-900">{{ $money($request->amount_requested) }} <span class="text-sm font-normal text-gray-400">· {{ $request->type }} {{ __('refund') }}</span></p>
                    <p class="text-sm text-gray-500">{{ __('Order') }} #{{ $request->order?->number }}</p>
                </div>
                <x-ui.badge :color="$request->status->color()" dot>{{ $request->status->label() }}</x-ui.badge>
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-4 border-t border-gray-100 pt-5 text-sm">
                <div><dt class="text-gray-400">{{ __('Buyer') }}</dt><dd class="font-medium text-gray-800">{{ $request->buyer?->name }}</dd></div>
                <div><dt class="text-gray-400">{{ __('Seller') }}</dt><dd class="font-medium text-gray-800">{{ $request->seller?->displayName() }}</dd></div>
                <div><dt class="text-gray-400">{{ __('Requested') }}</dt><dd class="text-gray-800">{{ $request->created_at?->format('M j, Y · g:i A') }}</dd></div>
                <div><dt class="text-gray-400">{{ __('Order total') }}</dt><dd class="text-gray-800">{{ $money($request->order?->total_amount) }}</dd></div>
            </dl>

            @if ($request->reason)
                <div class="mt-4 rounded-lg bg-gray-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Buyer’s reason') }}</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $request->reason }}</p>
                </div>
            @endif
            @if ($request->resolution_note)
                <div class="mt-3 rounded-lg bg-gray-50 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">{{ __('Resolution note') }} ({{ $request->resolver_type }})</p>
                    <p class="mt-1 text-sm text-gray-700">{{ $request->resolution_note }}</p>
                </div>
            @endif
        </x-ui.card>

        @if ($open)
            <x-ui.card>
                <p class="mb-3 text-sm font-semibold text-gray-900">{{ __('Resolve') }}</p>
                @error('refund')<p class="mb-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                <form method="POST" action="{{ route('admin.sell-refunds.approve', $request->id) }}" class="space-y-3">
                    @csrf
                    <textarea name="note" rows="2" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" placeholder="{{ __('Optional note') }}"></textarea>
                    <div class="flex gap-2">
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-600"><x-heroicon-o-check class="h-4 w-4" /> {{ __('Approve & refund') }} {{ $money($request->amount_requested) }}</button>
                        <button type="submit" formaction="{{ route('admin.sell-refunds.reject', $request->id) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-50"><x-heroicon-o-x-mark class="h-4 w-4" /> {{ __('Decline') }}</button>
                    </div>
                </form>
            </x-ui.card>
        @endif
    </div>
</x-layouts.admin>
