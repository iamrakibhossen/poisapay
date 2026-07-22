<x-layouts.admin :title="'Issued Cards'">
    <div class="space-y-6" x-data="{ viewingId: null }">
        <x-ui.page-header title="Issued Cards" subtitle="Every card the platform has provisioned. Freeze, inspect authorisations and post refunds." />

        {{-- Stat cards --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card label="Total cards" :value="number_format($stats['total'])" icon="credit-card" accent="brand" />
            <x-ui.stat-card label="Active" :value="number_format($stats['active'])" icon="check-circle" accent="emerald" />
            <x-ui.stat-card label="Frozen" :value="number_format($stats['frozen'])" icon="lock-closed" accent="amber" />
            <x-ui.stat-card label="Settled spend (this month)" :value="number_format($stats['spend'] / 100, 2)" icon="banknotes" accent="brand" />
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('admin.cards') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex flex-wrap gap-2">
                <x-ui.select name="status" class="w-auto" onchange="this.form.submit()">
                    <option value="all" @selected($status === 'all')>All statuses</option>
                    <option value="inactive" @selected($status === 'inactive')>Inactive</option>
                    <option value="active" @selected($status === 'active')>Active</option>
                    <option value="frozen" @selected($status === 'frozen')>Frozen</option>
                    <option value="closed" @selected($status === 'closed')>Closed</option>
                </x-ui.select>
                <x-ui.select name="type" class="w-auto" onchange="this.form.submit()">
                    <option value="all" @selected($type === 'all')>All types</option>
                    <option value="virtual" @selected($type === 'virtual')>Virtual</option>
                    <option value="physical" @selected($type === 'physical')>Physical</option>
                </x-ui.select>
            </div>

            <x-ui.input name="search" :value="$search" icon="magnifying-glass" placeholder="Search name, email or last4…" class="w-full sm:w-72" />
        </form>

        <x-ui.table :headers="['Cardholder', 'Card', 'Network', 'Type', 'Status', 'Limits', 'Created', '']">
            @forelse ($cards as $card)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-3 py-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$card->user?->name ?? '—'" size="sm" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-900">{{ $card->user?->name ?? '—' }}</p>
                                <p class="truncate text-xs text-neutral-500">{{ $card->user?->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-3 py-3">
                        <p class="text-sm font-semibold text-neutral-900">{{ $card->displayName() }}</p>
                        <p class="font-mono text-xs text-neutral-400">···· {{ $card->last4 }}</p>
                    </td>
                    <td class="px-3 py-3"><x-ui.badge :color="$card->network->value === 'visa' ? 'info' : 'warning'">{{ $card->network->label() }}</x-ui.badge></td>
                    <td class="px-3 py-3"><x-ui.badge :color="$card->type->color()">{{ $card->type->label() }}</x-ui.badge></td>
                    <td class="px-3 py-3"><x-ui.badge :color="$card->status->color()" dot>{{ $card->status->label() }}</x-ui.badge></td>
                    <td class="px-3 py-3 text-sm text-neutral-600">
                        <span class="tabular">{{ number_format($card->daily_limit / 100, 2) }}</span> / day<br>
                        <span class="tabular">{{ number_format($card->per_tx_limit / 100, 2) }}</span> / tx
                        <span class="text-xs text-neutral-400">{{ $card->settlement_currency }}</span>
                    </td>
                    <td class="px-3 py-3 text-sm text-neutral-500">{{ $card->created_at?->diffForHumans() }}</td>
                    <td class="px-3 py-3 text-right">
                        <x-ui.button type="button" x-on:click="viewingId = '{{ $card->id }}'" variant="secondary" size="sm" icon="eye">View</x-ui.button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8"><x-ui.empty-state icon="credit-card" title="No cards" description="No issued cards match your filters." /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $cards->links() }}

        {{-- Detail modals (one per card; Alpine-toggled — never exposes PAN/CVV) --}}
        @foreach ($cards as $card)
            <div x-show="viewingId === '{{ $card->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="viewingId = null"></div>
                <div class="relative flex max-h-[85vh] w-full max-w-2xl flex-col pp-card p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <div>
                            <h3 class="text-lg font-semibold text-neutral-900">{{ $card->displayName() }}</h3>
                            <p class="text-sm text-neutral-500">
                                {{ $card->user?->name }} · <span class="font-mono">···· {{ $card->last4 }}</span> · {{ $card->network->label() }}
                            </p>
                        </div>
                        <button type="button" x-on:click="viewingId = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>

                    <div class="mb-4 flex flex-wrap items-center gap-2">
                        <x-ui.badge :color="$card->status->color()" dot>{{ $card->status->label() }}</x-ui.badge>
                        <x-ui.badge :color="$card->type->color()">{{ $card->type->label() }}</x-ui.badge>
                        <span class="text-xs text-neutral-400">{{ $card->provider?->name }}</span>

                        @if (auth('admin')->user()?->can('manage-cards') || auth('admin')->user()?->hasRole('super-admin'))
                            @if ($card->status->value !== 'closed')
                                <div class="ml-auto">
                                    <form method="POST" action="{{ route('admin.cards.freeze', $card->id) }}"
                                        onsubmit="return confirm('{{ $card->status->value === 'frozen' ? 'Unfreeze this card and allow spending again?' : 'Freeze this card and block spending?' }}')">
                                        @csrf
                                        @if ($card->status->value === 'frozen')
                                            <x-ui.button type="submit" variant="secondary" size="sm" icon="lock-open">Unfreeze</x-ui.button>
                                        @else
                                            <x-ui.button type="submit" variant="danger" size="sm" icon="lock-closed">Freeze</x-ui.button>
                                        @endif
                                    </form>
                                </div>
                            @endif
                        @endif
                    </div>

                    <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">Recent authorisations</p>
                    <div class="min-h-0 flex-1 overflow-y-auto">
                        <x-ui.table :headers="['Merchant', 'Amount', 'MCC', 'Status', 'Date', '']">
                            @forelse (($authorizations[$card->id] ?? collect())->take(20) as $auth)
                                <tr class="border-b border-gray-200 hover:bg-gray-100">
                                    <td class="px-3 py-2.5 text-sm text-neutral-800">{{ $auth->merchant ?? '—' }}</td>
                                    <td class="px-3 py-2.5"><span class="tabular text-sm font-semibold text-neutral-900">{{ number_format($auth->amount / 100, 2) }}</span> <span class="text-xs text-neutral-400">{{ $auth->currency_code }}</span></td>
                                    <td class="px-3 py-2.5 font-mono text-xs text-neutral-500">{{ $auth->mcc ?? '—' }}</td>
                                    <td class="px-3 py-2.5"><x-ui.badge :color="$auth->status->color()">{{ $auth->status->label() }}</x-ui.badge></td>
                                    <td class="px-3 py-2.5 text-xs text-neutral-500">{{ $auth->created_at?->diffForHumans() }}</td>
                                    <td class="px-3 py-2.5 text-right">
                                        @if ($auth->status->value === 'settled' && (auth('admin')->user()?->can('manage-cards') || auth('admin')->user()?->hasRole('super-admin')))
                                            <form method="POST" action="{{ route('admin.cards.refund', $auth->id) }}"
                                                onsubmit="return confirm('Post a FULL refund for this settled purchase? This moves money back to the cardholder.')">
                                                @csrf
                                                <x-ui.button type="submit" variant="secondary" size="sm" icon="arrow-uturn-left">Refund</x-ui.button>
                                            </form>
                                        @else
                                            <span class="text-xs text-neutral-300">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6"><x-ui.empty-state icon="receipt-percent" title="No authorisations" description="This card has no activity yet." /></td></tr>
                            @endforelse
                        </x-ui.table>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <x-ui.button type="button" variant="secondary" x-on:click="viewingId = null">Close</x-ui.button>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.admin>
