<x-layouts.admin :title="__('Assets & chains')">
    @php
        // Which modal (if any) to reopen after a validation error: the network form
        // carries currency_id; the coin form does not.
        $reopen = old('currency_id') ? 'net' : ($errors->any() ? 'coin' : null);
    @endphp

    {{-- Alpine is light UI only: modal open/close + prefill. Forms POST traditionally. --}}
    <div x-data="{
            coinOpen: {{ $reopen === 'coin' ? 'true' : 'false' }},
            netOpen: {{ $reopen === 'net' ? 'true' : 'false' }},
            coin: {
                id: '{{ old('id') }}',
                symbol: @js(old('symbol', '')),
                name: @js(old('name', '')),
                kind: @js(old('kind', 'crypto')),
                currency_code: @js(old('currency_code', '')),
                is_stablecoin: {{ old('is_stablecoin') ? 'true' : 'false' }},
                sort: @js(old('sort', 0)),
                is_active: {{ old('is_active', $errors->any() ? null : '1') ? 'true' : 'false' }},
            },
            net: {
                id: '{{ old('id') }}',
                currency_id: @js(old('currency_id', '')),
                symbol: '',
                chain_id: @js(old('chain_id', '')),
                contract_address: @js(old('contract_address', '')),
                decimals: @js(old('decimals', 6)),
                withdrawal_min: @js(old('withdrawal_min', '0')),
                withdrawal_fee: @js(old('withdrawal_fee', '0')),
                sort: @js(old('sort', 0)),
                is_active: true,
            },
            newCoin() {
                this.coin = { id: '', symbol: '', name: '', kind: 'crypto', currency_code: '', is_stablecoin: false, sort: 0, is_active: true };
                this.coinOpen = true;
            },
            editCoin(c) {
                this.coin = { id: c.id, symbol: c.symbol, name: c.name, kind: c.kind, currency_code: c.currency_code ?? '', is_stablecoin: c.is_stablecoin, sort: c.sort, is_active: c.is_active };
                this.coinOpen = true;
            },
            addNetwork(c) {
                this.net = { id: '', currency_id: c.id, symbol: c.symbol, chain_id: '', contract_address: '', decimals: c.kind === 'fiat' ? 2 : 6, withdrawal_min: '0', withdrawal_fee: '0', sort: 0, is_active: true };
                this.netOpen = true;
            },
            editNetwork(a) {
                this.net = { id: a.id, currency_id: a.currency_id, symbol: a.symbol, chain_id: a.chain_id ? String(a.chain_id) : '', contract_address: a.contract_address ?? '', decimals: a.decimals, withdrawal_min: String(a.withdrawal_min ?? '0'), withdrawal_fee: String(a.withdrawal_fee ?? '0'), sort: a.sort, is_active: a.is_active };
                this.netOpen = true;
            },
        }" class="space-y-8">
        <x-ui.page-header :title="__('Assets & chains')" :subtitle="__('One coin, many networks — each coin settles on one or more chains.')">
            <x-slot:actions>
                <x-ui.button x-on:click="newCoin()" variant="primary" size="sm" icon="plus">{{ __('New coin') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Coins, each grouping its per-chain network rows --}}
        <div class="space-y-3">
            <h3 class="text-base font-semibold text-neutral-900">{{ __('Coins') }}</h3>
            <x-ui.table :headers="[__('Coin / network'), __('Contract'), __('Decimals'), __('Withdrawal fee'), __('Active'), '']">
                @forelse ($currencies as $currency)
                    @php
                        $coinJson = ['id' => $currency->id, 'symbol' => $currency->symbol, 'name' => $currency->name, 'kind' => $currency->kind->value, 'currency_code' => $currency->currency_code, 'is_stablecoin' => (bool) $currency->is_stablecoin, 'sort' => $currency->sort, 'is_active' => (bool) $currency->is_active];
                    @endphp

                    {{-- Coin header --}}
                    <tr class="border-t border-neutral-200 bg-neutral-50/70">
                        <td class="px-4 py-2.5" colspan="4">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <x-ui.asset-icon :symbol="$currency->symbol" size="sm" />
                                <span class="text-sm font-bold text-neutral-900">{{ $currency->symbol }}</span>
                                <span class="text-sm text-neutral-500">{{ $currency->name }}</span>
                                <x-ui.badge :color="$currency->kind->color()">{{ $currency->kind->label() }}</x-ui.badge>
                                @if ($currency->is_stablecoin)<x-ui.badge color="info">{{ __('Stablecoin') }}</x-ui.badge>@endif
                                <span class="rounded-full bg-neutral-200 px-2 py-0.5 text-xs font-medium text-neutral-600">
                                    {{ $currency->assets->count() }} {{ Str::plural('network', $currency->assets->count()) }}
                                </span>
                            </div>
                        </td>
                        <td class="px-4 py-2.5">
                            <form method="POST" action="{{ route('admin.currencies.toggle', $currency->id) }}">
                                @csrf
                                <button type="submit" title="{{ __('Toggle coin active') }}">
                                    <x-ui.badge :color="$currency->is_active ? 'success' : 'gray'" dot>{{ $currency->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                                </button>
                            </form>
                        </td>
                        <td class="px-4 py-2.5 text-right">
                            <div class="flex justify-end gap-1.5">
                                <x-ui.button variant="ghost" size="sm" icon="pencil-square" x-on:click="editCoin({{ Illuminate\Support\Js::from($coinJson) }})">{{ __('Edit coin') }}</x-ui.button>
                                <x-ui.button variant="ghost" size="sm" icon="plus" x-on:click="addNetwork({{ Illuminate\Support\Js::from($coinJson) }})">{{ __('Add network') }}</x-ui.button>
                            </div>
                        </td>
                    </tr>

                    {{-- Network rows --}}
                    @forelse ($currency->assets as $asset)
                        <tr class="hover:bg-neutral-50">
                            <td class="py-3 pl-10 pr-4">
                                <div class="flex items-center gap-2">
                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span>
                                    @if ($asset->chain)
                                        <span class="text-sm font-medium text-neutral-800">{{ $asset->chain->name }}</span>
                                    @elseif ($asset->kind->value === 'fiat')
                                        <span class="text-sm font-medium text-neutral-800">{{ __('Fiat') }}{{ $asset->currency_code ? ' · '.$asset->currency_code : '' }}</span>
                                    @else
                                        <span class="text-sm text-neutral-400">{{ __('No chain') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($asset->contract_address)
                                    <span class="tabular truncate font-mono text-xs text-neutral-500" title="{{ $asset->contract_address }}">{{ Str::limit($asset->contract_address, 18) }}</span>
                                @else
                                    <span class="text-neutral-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm text-neutral-600"><span class="tabular">{{ $asset->decimals }}</span></td>
                            <td class="px-4 py-3 text-sm text-neutral-600"><span class="tabular">{{ $asset->withdrawal_fee ?? '0' }}</span></td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('admin.assets.toggle', $asset->id) }}">
                                    @csrf
                                    <button type="submit" title="{{ __('Toggle network active') }}">
                                        <x-ui.badge :color="$asset->is_active ? 'success' : 'gray'" dot>{{ $asset->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                                    </button>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-ui.button variant="ghost" size="sm" icon="pencil-square"
                                    x-on:click="editNetwork({{ Illuminate\Support\Js::from(['id' => $asset->id, 'currency_id' => $asset->currency_id, 'symbol' => $asset->symbol, 'chain_id' => $asset->chain_id, 'contract_address' => $asset->contract_address, 'decimals' => $asset->decimals, 'withdrawal_min' => (string) ($asset->withdrawal_min ?? '0'), 'withdrawal_fee' => (string) ($asset->withdrawal_fee ?? '0'), 'sort' => $asset->sort, 'is_active' => (bool) $asset->is_active]) }})">{{ __('Edit') }}</x-ui.button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="py-3 pl-10 pr-4 text-sm text-neutral-400" colspan="6">
                                {{ __('No networks yet —') }} <button type="button" class="font-medium text-brand-700 hover:underline" x-on:click="addNetwork({{ Illuminate\Support\Js::from($coinJson) }})">{{ __('add one') }}</button>.
                            </td>
                        </tr>
                    @endforelse
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="banknotes" :title="__('No coins')" :description="__('Create your first coin to get started.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>

        {{-- Chains --}}
        <div class="space-y-3">
            <h3 class="text-base font-semibold text-neutral-900">{{ __('Chains') }}</h3>
            <x-ui.table :headers="[__('Key'), __('Name'), __('Native symbol'), __('Min confirmations'), __('EVM'), __('Active')]">
                @forelse ($chains as $chain)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-3"><x-ui.badge :color="$chain->key->color()">{{ $chain->key->label() }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-sm font-medium text-neutral-900">{{ $chain->name }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600">{{ $chain->native_symbol }}</td>
                        <td class="px-4 py-3 text-sm text-neutral-600"><span class="tabular">{{ $chain->min_confirmations }}</span></td>
                        <td class="px-4 py-3">
                            @if ($chain->is_evm)
                                <x-heroicon-s-check-circle class="h-5 w-5 text-emerald-500" />
                            @else
                                <span class="text-neutral-300">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3"><x-ui.badge :color="$chain->is_active ? 'success' : 'gray'" dot>{{ $chain->is_active ? __('Active') : __('Inactive') }}</x-ui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="link" :title="__('No chains')" :description="__('No settlement chains configured.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>

        {{-- Coin (currency) modal --}}
        <div x-show="coinOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500/60" x-on:click="coinOpen = false"></div>
            <div class="relative w-full max-w-lg pp-card p-6" role="dialog" aria-modal="true">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-neutral-900" x-text="coin.id ? 'Edit coin' : 'New coin'"></h3>
                    <button type="button" x-on:click="coinOpen = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <form method="POST" action="{{ route('admin.currencies.save') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="id" :value="coin.id" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input name="symbol" x-model="coin.symbol" :label="__('Symbol')" :placeholder="__('USDT')" :error="$errors->first('symbol')" />
                        <x-ui.input name="name" x-model="coin.name" :label="__('Name')" :placeholder="__('Tether USD')" :error="$errors->first('name')" />
                        <x-ui.select :label="__('Kind')" name="kind" x-model="coin.kind" :error="$errors->first('kind')">
                            <option value="crypto">{{ __('Crypto') }}</option>
                            <option value="fiat">{{ __('Fiat') }}</option>
                        </x-ui.select>
                        <x-ui.input name="sort" x-model="coin.sort" type="number" :label="__('Sort')" :error="$errors->first('sort')" />
                    </div>
                    <div class="flex flex-wrap gap-6">
                        <x-ui.checkbox name="is_stablecoin" value="1" x-model="coin.is_stablecoin" :label="__('Stablecoin')" />
                        <x-ui.checkbox name="is_active" value="1" x-model="coin.is_active" :label="__('Active')" />
                    </div>
                    <p class="rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-500">{{ __("The coin's identity is shared by all its networks. Editing it re-syncs every network row.") }}</p>
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="coinOpen = false">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" variant="primary" x-text="coin.id ? 'Save coin' : 'Create coin'"></x-ui.button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Network (asset) modal --}}
        <div x-show="netOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500/60" x-on:click="netOpen = false"></div>
            <div class="relative w-full max-w-lg pp-card p-6" role="dialog" aria-modal="true">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-neutral-900">
                        <span x-text="net.id ? 'Edit network' : 'Add network'"></span>
                        <span class="text-neutral-400">·</span>
                        <span class="font-bold" x-text="net.symbol"></span>
                    </h3>
                    <button type="button" x-on:click="netOpen = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <form method="POST" action="{{ route('admin.assets.save') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="id" :value="net.id" />
                    <input type="hidden" name="currency_id" :value="net.currency_id" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select :label="__('Chain')" name="chain_id" x-model="net.chain_id" :error="$errors->first('chain_id')">
                            <option value="">{{ __('— None (fiat / chain-less) —') }}</option>
                            @foreach ($chainOptions as $id => $chainName)
                                <option value="{{ $id }}">{{ $chainName }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input name="contract_address" x-model="net.contract_address" :label="__('Contract address')" :placeholder="__('Leave blank for native')" :error="$errors->first('contract_address')" />
                        <x-ui.input name="decimals" x-model="net.decimals" type="number" :label="__('Decimals')" :error="$errors->first('decimals')" />
                        <x-ui.input name="sort" x-model="net.sort" type="number" :label="__('Sort')" :error="$errors->first('sort')" />
                        <x-ui.input name="withdrawal_min" x-model="net.withdrawal_min" :label="__('Withdrawal min (base units)')" :error="$errors->first('withdrawal_min')" />
                        <x-ui.input name="withdrawal_fee" x-model="net.withdrawal_fee" :label="__('Withdrawal fee (base units)')" :error="$errors->first('withdrawal_fee')" />
                    </div>
                    <x-ui.checkbox name="is_active" value="1" x-model="net.is_active" :label="__('Active')" />
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="netOpen = false">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" variant="primary" x-text="net.id ? 'Save network' : 'Add network'"></x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
