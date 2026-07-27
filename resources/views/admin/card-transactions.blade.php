<x-layouts.admin :title="__('Card Transactions')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Card Transactions')" :subtitle="__('Card authorizations — approved, settled, declined and reversed.')" />

        <form method="GET" action="{{ route('admin.card-transactions') }}">
            <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
                @foreach ($tabs as $key => $count)
                    <a href="{{ route('admin.card-transactions', ['status' => $key]) }}"
                        @class([
                            'flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium capitalize transition',
                            'bg-white text-neutral-900 shadow-sm' => $status === $key,
                            'text-neutral-500 hover:text-neutral-800' => $status !== $key,
                        ])>
                        {{ $key }}
                        <span class="rounded-full bg-neutral-200 px-1.5 text-xs">{{ $count }}</span>
                    </a>
                @endforeach
            </div>
        </form>

        <x-ui.table :headers="[__('Cardholder'), __('Merchant'), __('Amount'), __('Funding'), __('Status'), __('When')]">
            @forelse ($transactions as $t)
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3">
                        <p class="truncate text-sm font-medium text-neutral-900">{{ $t->card?->user?->name ?? '—' }}</p>
                        <p class="truncate text-xs text-neutral-500">···· {{ $t->card?->last4 }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <p class="text-sm text-neutral-700">{{ $t->merchant ?? '—' }}</p>
                        @if ($t->mcc)<p class="text-xs text-neutral-400">MCC {{ $t->mcc }}</p>@endif
                    </td>
                    <td class="px-4 py-3"><span class="tabular text-sm font-semibold text-neutral-900">{{ number_format((int) $t->amount / 100, 2) }} {{ $t->currency_code }}</span></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">
                        {{ $t->fundingSourceLabel() }}
                        @if ($t->exchangeRateLabel())<span class="block text-xs text-neutral-400">{{ $t->exchangeRateLabel() }}</span>@endif
                    </td>
                    <td class="px-4 py-3"><x-ui.badge :color="$t->status->color()" dot>{{ $t->status->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $t->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state icon="credit-card" :title="__('No card transactions')" :description="__('Authorizations appear here as cards are used.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $transactions->links() }}
    </div>
</x-layouts.admin>
