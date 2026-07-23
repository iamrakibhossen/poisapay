<x-layouts.admin :title="__('Transfers')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Transfers')" :subtitle="__('P2P sends, fiat payouts and cross-border remittances.')" />

        {{-- Kind tabs (query-string filter) --}}
        <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
            @foreach ($tabs as $key => $count)
                <a href="{{ route('admin.transfers', ['kind' => $key]) }}"
                    @class([
                        'flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium capitalize transition',
                        'bg-white text-neutral-900 shadow-sm' => $kind === $key,
                        'text-neutral-500 hover:text-neutral-800' => $kind !== $key,
                    ])>
                    {{ $key }}
                    <span class="rounded-full bg-neutral-200 px-1.5 text-xs">{{ $count }}</span>
                </a>
            @endforeach
        </div>

        <x-ui.table :headers="[__('Sender'), __('Recipient'), __('Asset'), __('Amount'), __('Kind'), __('Status'), __('Created')]">
            @forelse ($transfers as $transfer)
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$transfer->sender?->name ?? '?'" size="sm" />
                            <p class="truncate text-sm font-medium text-neutral-900">{{ $transfer->sender?->name ?? '—' }}</p>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-sm text-neutral-700">{{ $transfer->recipient?->name ?? $transfer->recipient_handle ?? '—' }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-2">
                            <x-ui.asset-icon :symbol="$transfer->asset->symbol" size="sm" />
                            <span class="text-sm font-medium text-neutral-900">{{ $transfer->asset->symbol }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3"><span class="tabular text-sm font-semibold text-neutral-900">{{ $transfer->money()->format() }}</span></td>
                    <td class="px-4 py-3"><x-ui.badge :color="$transfer->kind->color()">{{ $transfer->kind->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3"><x-ui.badge :color="$transfer->status->color()" dot>{{ $transfer->status->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $transfer->created_at->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="arrows-right-left" :title="__('No transfers')" :description="__('Nothing in this queue.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $transfers->links() }}
    </div>
</x-layouts.admin>
