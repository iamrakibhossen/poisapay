<x-layouts.admin :title="__('Settlements')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Settlements')" :subtitle="__('Revenue payouts — pending approval, in flight and completed.')">
            <x-slot:actions>
                <x-ui.button :href="route('admin.revenue')" variant="secondary" size="sm" icon="banknotes">{{ __('Approve on Revenue') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <form method="GET" action="{{ route('admin.settlements') }}">
            <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
                @foreach ($tabs as $key => $count)
                    <a href="{{ route('admin.settlements', ['status' => $key]) }}"
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

        <x-ui.table :headers="[__('Asset'), __('Amount'), __('Network'), __('Destination'), __('Status'), __('Requested by'), __('When')]">
            @forelse ($settlements as $s)
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3 text-sm font-medium text-neutral-900">{{ $s->asset?->symbol ?? '—' }}</td>
                    <td class="px-4 py-3"><span class="tabular text-sm font-semibold text-neutral-900">{{ $s->asset ? $s->asset->money($s->amount)->format() : $s->amount }}</span></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $s->network ?? '—' }}</td>
                    <td class="px-4 py-3"><span class="font-mono text-xs text-neutral-500">{{ Str::limit($s->destination_address, 18) }}</span></td>
                    <td class="px-4 py-3"><x-ui.badge :color="$s->status->color()" dot>{{ $s->status->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $s->creator?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $s->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="check-badge" :title="__('No settlements')" :description="__('Revenue payouts appear here once requested.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $settlements->links() }}
    </div>
</x-layouts.admin>
