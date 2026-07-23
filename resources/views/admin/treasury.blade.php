<x-layouts.admin :title="__('Treasury')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Treasury & solvency')" :subtitle="__('Prove ledger treasury ≥ user liability, per asset.')">
            <x-slot:actions>
                <form method="POST" action="{{ route('admin.treasury.reconcile') }}"
                    onsubmit="return confirm('{{ __('Run reconciliation across all active assets now?') }}')">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm" icon="arrow-path">{{ __('Run reconciliation now') }}</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Per-asset solvency cards --}}
        @if ($assets->isEmpty())
            <x-ui.card><x-ui.empty-state icon="banknotes" :title="__('No active assets')" :description="__('Enable an asset to track treasury solvency.')" /></x-ui.card>
        @else
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($assets as $asset)
                    @php($run = $latestByAsset[$asset->id] ?? null)
                    <div class="pp-card p-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <x-ui.asset-icon :symbol="$asset->symbol" size="sm" />
                                <span class="text-sm font-semibold text-neutral-900">{{ $asset->symbol }}</span>
                            </div>
                            @if ($run)
                                <x-ui.badge :color="$run->is_solvent ? 'success' : 'danger'" dot>{{ $run->is_solvent ? __('Solvent') : __('Insolvent') }}</x-ui.badge>
                            @else
                                <x-ui.badge color="gray">{{ __('No runs yet') }}</x-ui.badge>
                            @endif
                        </div>

                        @if ($run)
                            <dl class="mt-4 space-y-2 text-sm">
                                <div class="flex items-center justify-between">
                                    <dt class="text-neutral-500">{{ __('Treasury balance') }}</dt>
                                    <dd class="tabular font-semibold text-neutral-900">{{ $asset->money($run->ledger_treasury)->format() }}</dd>
                                </div>
                                <div class="flex items-center justify-between">
                                    <dt class="text-neutral-500">{{ __('User liability') }}</dt>
                                    <dd class="tabular font-semibold text-neutral-900">{{ $asset->money($run->ledger_liability)->format() }}</dd>
                                </div>
                                <div class="flex items-center justify-between border-t border-neutral-100 pt-2">
                                    <dt class="text-neutral-500">{{ __('Drift') }}</dt>
                                    <dd class="tabular font-semibold {{ $run->is_solvent ? 'text-emerald-600' : 'text-rose-600' }}">{{ $asset->money($run->drift)->format() }}</dd>
                                </div>
                            </dl>
                            <p class="mt-3 text-xs text-neutral-400">{{ __('Last run') }} {{ $run->created_at->diffForHumans() }}</p>
                        @else
                            <p class="mt-4 text-sm text-neutral-500">{{ __('No reconciliation runs recorded yet.') }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Recent runs --}}
        <div class="space-y-3">
            <div>
                <h3 class="text-base font-semibold text-neutral-900">{{ __('Recent reconciliation runs') }}</h3>
                <p class="mt-0.5 text-sm text-neutral-500">{{ __('Latest solvency checks across assets') }}</p>
            </div>
            <x-ui.table :headers="[__('Asset'), __('Treasury'), __('Liability'), __('Drift'), __('Solvent'), __('Status'), __('When')]">
                @forelse ($recentRuns as $run)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <x-ui.asset-icon :symbol="$run->asset->symbol" size="sm" />
                                <span class="text-sm font-medium text-neutral-900">{{ $run->asset->symbol }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3"><span class="tabular text-sm text-neutral-700">{{ $run->asset->money($run->ledger_treasury)->format() }}</span></td>
                        <td class="px-4 py-3"><span class="tabular text-sm text-neutral-700">{{ $run->asset->money($run->ledger_liability)->format() }}</span></td>
                        <td class="px-4 py-3"><span class="tabular text-sm font-medium {{ $run->is_solvent ? 'text-emerald-600' : 'text-rose-600' }}">{{ $run->asset->money($run->drift)->format() }}</span></td>
                        <td class="px-4 py-3"><x-ui.badge :color="$run->is_solvent ? 'success' : 'danger'" dot>{{ $run->is_solvent ? __('Yes') : __('No') }}</x-ui.badge></td>
                        <td class="px-4 py-3"><span class="text-sm capitalize text-neutral-600">{{ $run->status }}</span></td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $run->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-ui.empty-state icon="scale" :title="__('No runs yet')" :description="__('Run reconciliation to record a solvency check.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-layouts.admin>
