<x-layouts.admin :title="__('Ledger')">
    <div class="space-y-6" x-data="{ reversingId: null }">
        <x-ui.page-header :title="__('Ledger')" :subtitle="__('Double-entry journal. Every money movement posts a balanced set of lines.')" />

        <form method="GET" action="{{ route('admin.ledger') }}" class="flex justify-end">
            <x-ui.input name="search" :value="$search" icon="magnifying-glass" :placeholder="__('Search type or idempotency key…')" class="w-full sm:w-80" />
        </form>

        <x-ui.table :headers="[__('Entry'), __('Type'), __('Status'), __('Memo'), __('Lines'), __('Created'), '']">
            @forelse ($entries as $entry)
                @php $isExpanded = $expandedId === $entry->id; @endphp
                <tr class="cursor-pointer hover:bg-neutral-50">
                    <td class="px-4 py-3">
                        <a href="{{ route('admin.ledger', array_merge(request()->query(), ['entry' => $isExpanded ? null : $entry->id])) }}" class="flex items-center gap-2">
                            <x-heroicon-o-chevron-right @class(['h-4 w-4 text-neutral-400 transition-transform', 'rotate-90' => $isExpanded])/>
                            <span class="font-mono text-xs text-neutral-500">{{ \Illuminate\Support\Str::limit($entry->id, 8, '') }}</span>
                        </a>
                    </td>
                    <td class="px-4 py-3"><span class="text-sm font-medium text-neutral-900">{{ $entry->type }}</span></td>
                    <td class="px-4 py-3">
                        <x-ui.badge :color="$entry->status->color()" dot>{{ $entry->status->label() }}</x-ui.badge>
                        @if ($entry->reverses_entry_id)
                            <x-ui.badge color="gray">{{ __('Reversal') }}</x-ui.badge>
                        @endif
                    </td>
                    <td class="px-4 py-3"><span class="text-sm text-neutral-600">{{ $entry->memo ?? '—' }}</span></td>
                    <td class="px-4 py-3 text-sm text-neutral-600"><span class="tabular">{{ $entry->lines_count }}</span></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $entry->created_at->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        @if ($canReverse && $entry->status !== \App\Enums\EntryStatus::Reversed && $entry->reverses_entry_id === null)
                            <x-ui.button type="button" x-on:click="reversingId = '{{ $entry->id }}'" variant="danger" size="sm" icon="arrow-uturn-left">{{ __('Reverse') }}</x-ui.button>
                        @endif
                    </td>
                </tr>

                @if ($isExpanded && $lines)
                    <tr class="bg-neutral-50/70">
                        <td colspan="7" class="px-4 py-3">
                            <div class="overflow-hidden rounded-xl border border-neutral-200">
                                <table class="min-w-full divide-y divide-neutral-200">
                                    <thead class="bg-neutral-100/70">
                                        <tr>
                                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-neutral-500">{{ __('Side') }}</th>
                                            <th class="px-4 py-2 text-left text-[11px] font-semibold uppercase tracking-wider text-neutral-500">{{ __('Account') }}</th>
                                            <th class="px-4 py-2 text-right text-[11px] font-semibold uppercase tracking-wider text-neutral-500">{{ __('Amount') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-neutral-100">
                                        @foreach ($lines as $line)
                                            <tr>
                                                <td class="px-4 py-2">
                                                    <x-ui.badge :color="$line->side->value === 'debit' ? 'info' : 'primary'">{{ ucfirst($line->side->value) }}</x-ui.badge>
                                                </td>
                                                <td class="px-4 py-2 text-sm text-neutral-700">{{ $line->account?->type->label() ?? '—' }}</td>
                                                <td class="px-4 py-2 text-right"><span class="tabular text-sm font-semibold text-neutral-900">{{ $line->asset->money($line->amount)->format() }}</span></td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="7"><x-ui.empty-state icon="book-open" :title="__('No journal entries')" :description="__('Nothing posted to the ledger yet.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $entries->links() }}

        {{-- Reverse entry modal (Alpine-driven; one POST per entry with an optional reason) --}}
        @if ($canReverse)
            @foreach ($entries as $entry)
                @if ($entry->status !== \App\Enums\EntryStatus::Reversed && $entry->reverses_entry_id === null)
                    <div x-show="reversingId === '{{ $entry->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                        <div class="fixed inset-0 bg-gray-500/60" x-on:click="reversingId = null"></div>
                        <div class="relative w-full max-w-md pp-card p-6" role="dialog" aria-modal="true">
                            <div class="mb-4 flex items-center justify-between">
                                <h3 class="text-lg font-semibold text-neutral-900">{{ __('Reverse entry') }}</h3>
                                <button type="button" x-on:click="reversingId = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                            </div>
                            <form method="POST" action="{{ route('admin.ledger.reverse', $entry->id) }}" class="space-y-4">
                                @csrf
                                <p class="text-sm text-neutral-600">
                                    {{ __('This posts a new balanced reversing entry and marks the original as') }}
                                    <span class="font-semibold">{{ __('reversed') }}</span>. {{ __('This cannot be undone.') }}
                                </p>
                                <x-ui.input :label="__('Reason (optional)')" name="reason" :placeholder="__('e.g. Duplicate posting')" class="w-full" />
                                <div class="flex justify-end gap-2">
                                    <x-ui.button type="button" variant="ghost" size="sm" x-on:click="reversingId = null">{{ __('Cancel') }}</x-ui.button>
                                    <x-ui.button type="submit" variant="danger" size="sm" icon="arrow-uturn-left">{{ __('Reverse entry') }}</x-ui.button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endforeach
        @endif
    </div>
</x-layouts.admin>
