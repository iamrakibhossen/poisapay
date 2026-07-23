<x-layouts.admin :title="__('Card Disputes')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Card Disputes')" :subtitle="__('Adjudicate cardholder disputes. Losing a case posts a chargeback that reimburses the cardholder.')" />

        {{-- Stat cards --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card :label="__('Open disputes')" :value="number_format($stats['open'])" icon="exclamation-triangle" accent="amber" />
            <x-ui.stat-card :label="__('Open disputed amount')" :value="number_format($stats['openAmount'] / 100, 2)" icon="banknotes" accent="brand" />
            <x-ui.stat-card :label="__('Won')" :value="number_format($stats['won'])" icon="check-circle" accent="emerald" />
            <x-ui.stat-card :label="__('Lost (chargebacks)')" :value="number_format($stats['lost'])" icon="x-circle" accent="rose" />
        </div>

        {{-- Filter tabs (query-string filter) --}}
        @php
            $filters = ['actionable' => __('Actionable'), 'all' => __('All'), 'open' => __('Open'), 'represented' => __('Represented'), 'won' => __('Won'), 'lost' => __('Lost')];
        @endphp
        <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
            @foreach ($filters as $key => $label)
                <a href="{{ route('admin.card-disputes', ['filter' => $key]) }}"
                    @class([
                        'flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium transition',
                        'bg-white text-neutral-900 shadow-sm' => $filter === $key,
                        'text-neutral-500 hover:text-neutral-800' => $filter !== $key,
                    ])>
                    {{ $label }}
                </a>
            @endforeach
        </div>

        @php
            $statusColor = ['open' => 'warning', 'represented' => 'warning', 'won' => 'success', 'lost' => 'danger'];
        @endphp

        <x-ui.table :headers="[__('Cardholder'), __('Merchant'), __('Reason'), __('Amount'), __('Status'), __('Opened'), '']">
            @forelse ($disputes as $dispute)
                @php
                    $auth = $dispute->authorization;
                    $user = $auth?->card?->user;
                    $actionable = in_array($dispute->status, ['open', 'represented'], true);
                @endphp
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$user?->name ?? '—'" size="sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-900">{{ $user?->name ?? '—' }}</p>
                                <p class="truncate text-xs text-neutral-500">{{ $user?->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3 text-sm text-neutral-800">{{ $auth?->merchant ?? '—' }}</td>
                    <td class="px-3 py-3 text-sm text-neutral-600">{{ $dispute->reason ?? '—' }}</td>
                    <td class="px-3 py-3">
                        <span class="tabular text-sm font-semibold text-neutral-900">{{ number_format($dispute->amount / 100, 2) }}</span>
                        <span class="text-xs text-neutral-400">{{ $auth?->currency_code }}</span>
                    </td>
                    <td class="px-3 py-3"><x-ui.badge :color="$statusColor[$dispute->status] ?? 'gray'" dot>{{ ucfirst($dispute->status) }}</x-ui.badge></td>
                    <td class="px-3 py-3 text-sm text-neutral-500">{{ $dispute->created_at?->diffForHumans() }}</td>
                    <td class="px-3 py-3 text-right">
                        @if ($actionable && (auth('admin')->user()?->can('manage-card-disputes') || auth('admin')->user()?->hasRole('super-admin')))
                            <div class="flex justify-end gap-2">
                                <form method="POST" action="{{ route('admin.card-disputes.resolve', $dispute->id) }}"
                                    onsubmit="return confirm('{{ __('Mark this dispute as WON and close it? No money moves.') }}')">
                                    @csrf
                                    <input type="hidden" name="outcome" value="won" />
                                    <x-ui.button type="submit" variant="success" size="sm" icon="check">{{ __('Won') }}</x-ui.button>
                                </form>
                                <form method="POST" action="{{ route('admin.card-disputes.resolve', $dispute->id) }}"
                                    onsubmit="return confirm('{{ __('Mark this dispute as LOST? This posts a chargeback that reimburses the cardholder.') }}')">
                                    @csrf
                                    <input type="hidden" name="outcome" value="lost" />
                                    <x-ui.button type="submit" variant="danger" size="sm" icon="banknotes">{{ __('Lost') }}</x-ui.button>
                                </form>
                            </div>
                        @else
                            <span class="text-xs text-neutral-400">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="scale" :title="__('No disputes')" :description="__('Nothing matches this filter.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $disputes->links() }}
    </div>
</x-layouts.admin>
