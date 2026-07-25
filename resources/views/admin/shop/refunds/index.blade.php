<x-layouts.admin :title="__('Refund review')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Refund review')"
            :subtitle="__('Buyer refund requests. Escalated ones need an operator decision.')" />

        {{-- Status filter --}}
        <div class="flex flex-wrap gap-2">
            @foreach (['escalated' => __('Escalated'), 'requested' => __('Open'), 'refunded' => __('Refunded'), 'rejected' => __('Rejected'), 'all' => __('All')] as $val => $lbl)
                <a href="{{ route('admin.shop-refunds', ['status' => $val]) }}"
                    @class([
                        'rounded-lg px-3 py-1.5 text-sm font-semibold transition',
                        'bg-ink-900 text-white' => $status === $val,
                        'border border-gray-200 text-gray-600 hover:bg-gray-50' => $status !== $val,
                    ])>
                    {{ $lbl }}
                    @if ($val === 'escalated' && $escalatedCount > 0)<span class="ml-1 rounded-full bg-blue-100 px-1.5 text-xs text-blue-700">{{ $escalatedCount }}</span>@endif
                </a>
            @endforeach
        </div>

        <x-ui.card>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 text-left text-xs font-semibold uppercase tracking-wide text-gray-400">
                            <th class="px-4 py-3">{{ __('Order') }}</th>
                            <th class="px-4 py-3">{{ __('Buyer') }}</th>
                            <th class="px-4 py-3">{{ __('Seller') }}</th>
                            <th class="px-4 py-3">{{ __('Amount') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3">{{ __('Requested') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @forelse ($requests as $req)
                            @php $asset = $req->order?->asset; $amt = $asset ? $asset->money((string) (int) $req->amount_requested)->format(2) : (string) $req->amount_requested; @endphp
                            <tr class="transition hover:bg-gray-50/60">
                                <td class="px-4 py-3 font-mono text-xs text-gray-500">#{{ $req->order?->number }}</td>
                                <td class="px-4 py-3 text-gray-800">{{ $req->buyer?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-800">{{ $req->seller?->displayName() ?? '—' }}</td>
                                <td class="px-4 py-3"><span class="font-semibold text-gray-900">{{ $amt }}</span> <span class="text-xs text-gray-400">· {{ $req->type }}</span></td>
                                <td class="px-4 py-3"><x-ui.badge :color="$req->status->color()" dot>{{ $req->status->label() }}</x-ui.badge></td>
                                <td class="px-4 py-3 text-xs text-gray-400">{{ $req->created_at?->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right"><a href="{{ route('admin.shop-refunds.show', $req->id) }}" class="text-sm font-semibold text-brand-600 hover:text-brand-700">{{ __('Review') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-gray-400">{{ __('No refund requests here.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($requests->hasPages())<div class="mt-4">{{ $requests->links() }}</div>@endif
        </x-ui.card>
    </div>
</x-layouts.admin>
