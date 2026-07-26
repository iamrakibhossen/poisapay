<x-layouts.admin :title="__('P2P Merchants')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('P2P Merchants')" :subtitle="__('Reputation overview and featured-promotion controls.')" />

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif

        <form method="GET" action="{{ route('admin.p2p-merchants') }}" class="max-w-md">
            <x-ui.input name="search" :value="$search" icon="magnifying-glass" :placeholder="__('Search by name or email…')" />
        </form>

        @if ($merchants->isEmpty())
            <x-ui.card><x-ui.empty-state icon="user-group" :title="__('No merchants yet')" /></x-ui.card>
        @else
            <x-ui.table :headers="[
                ['label' => __('Merchant')],
                ['label' => __('Level')],
                ['label' => __('Trades'), 'align' => 'right'],
                ['label' => __('Completion'), 'align' => 'right'],
                ['label' => __('Avg release'), 'align' => 'right'],
                ['label' => __('Featured'), 'align' => 'right'],
                ['label' => '', 'align' => 'right'],
            ]">
                @foreach ($merchants as $m)
                    <tr>
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2.5">
                                <x-ui.avatar :name="$m->user?->name ?? '—'" size="sm" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ $m->user?->name ?? '—' }}</p>
                                    <p class="truncate text-xs text-neutral-400">{{ $m->user?->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-sm">{{ (int) $m->level }}</td>
                        <td class="px-5 py-3 text-right text-sm tabular">{{ number_format($m->trade_count) }}</td>
                        <td class="px-5 py-3 text-right text-sm tabular">{{ number_format($m->completion_rate_bps / 100, 1) }}%</td>
                        <td class="px-5 py-3 text-right text-sm tabular">{{ $m->avg_release_seconds ? max(1, (int) round($m->avg_release_seconds / 60)).'m' : '—' }}</td>
                        <td class="px-5 py-3 text-right">
                            @if ($m->isFeatured())
                                <x-ui.badge color="warning" icon="sparkles">{{ __('until') }} {{ $m->featured_until->format('M j') }}</x-ui.badge>
                            @else
                                <span class="text-xs text-neutral-400">—</span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-right">
                            <form method="POST" action="{{ route('admin.p2p-merchants.feature', $m->user_id) }}">
                                @csrf
                                <x-ui.button type="submit" size="sm" :variant="$m->isFeatured() ? 'secondary' : 'primary'" :icon="$m->isFeatured() ? 'x-mark' : 'sparkles'">
                                    {{ $m->isFeatured() ? __('Unfeature') : __('Feature') }}
                                </x-ui.button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </x-ui.table>

            <div>{{ $merchants->links() }}</div>
        @endif
    </div>
</x-layouts.admin>
