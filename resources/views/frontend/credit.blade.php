<x-layouts.app :title="'Credit'">
    <div class="space-y-6">
        <x-ui.page-header title="Credit" subtitle="Borrow stablecoins against your crypto — without selling it." />

        <x-ui.alert type="info" title="Designed, later-phase product">
            Crypto-backed credit is specified in the PoisaPay TDD (§F6) and is <span class="font-semibold">lending-permission gated</span>
            for a future phase. The flows below are fully functional against the ledger for demonstration.
        </x-ui.alert>

        @if ($line)
            {{-- Active line --}}
            <div class="space-y-6">
                {{-- Hero credit line card --}}
                <div class="relative overflow-hidden rounded-2xl border border-brand-200 bg-gradient-to-br from-brand-50 to-brand-100 p-6 shadow-[var(--shadow-card)]">
                    <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 85% 10%, black 1px, transparent 1px); background-size: 26px 26px;"></div>
                    <div class="relative">
                        <div class="flex items-start justify-between">
                            <div>
                                <p class="text-sm text-neutral-600">Credit line</p>
                                <div class="mt-1 flex items-center gap-2 text-neutral-900">
                                    <x-heroicon-s-banknotes class="h-5 w-5" />
                                    <span class="text-lg font-semibold">{{ $line['collateralSymbol'] }} → {{ $line['principalSymbol'] }}</span>
                                </div>
                            </div>
                            <span class="rounded-full bg-white px-2.5 py-0.5 text-xs font-medium text-neutral-800 ring-1 ring-brand-200">{{ $line['status']['label'] }}</span>
                        </div>

                        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div>
                                <p class="text-xs text-neutral-600">Collateral pledged</p>
                                <p class="tabular mt-0.5 text-sm font-semibold text-neutral-900">{{ $line['collateral'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-neutral-600">Principal drawn</p>
                                <p class="tabular mt-0.5 text-sm font-semibold text-neutral-900">{{ $line['principal'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-neutral-600">Accrued fee</p>
                                <p class="tabular mt-0.5 text-sm font-semibold text-neutral-900">{{ $line['fee'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-neutral-600">Available to draw</p>
                                <p class="tabular mt-0.5 text-sm font-semibold text-neutral-900">{{ $line['availableToDraw'] }}</p>
                            </div>
                        </div>

                        {{-- LTV progress bar --}}
                        <div class="mt-6">
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-neutral-600">Loan-to-value</span>
                                <span class="tabular font-semibold text-neutral-900">{{ $line['ltvPercent'] }}%</span>
                            </div>
                            <div class="mt-1.5 h-2.5 w-full overflow-hidden rounded-full bg-neutral-200">
                                <div class="h-full rounded-full transition-all {{ $line['barColor'] }}" style="width: {{ $line['barPct'] }}%"></div>
                            </div>
                            <div class="mt-1 flex justify-between text-[10px] text-neutral-600">
                                <span>Max {{ $line['maxLtvPercent'] }}%</span>
                                <span>Liquidation {{ $line['liqLtvPercent'] }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Draw / Repay forms --}}
                <div class="grid gap-6 sm:grid-cols-2">
                    <x-ui.card title="Draw credit">
                        <form method="POST" action="{{ route('credit.draw') }}" class="space-y-4">
                            @csrf
                            <x-ui.input :label="'Amount (' . $line['principalSymbol'] . ')'" name="drawAmount" :value="old('drawAmount')"
                                type="text" inputmode="decimal" placeholder="0.00" :error="$errors->first('drawAmount')"
                                :hint="'Available: ' . $line['availableToDraw']" />
                            <x-ui.button type="submit" class="w-full" icon="arrow-down-tray">Draw</x-ui.button>
                        </form>
                    </x-ui.card>

                    <x-ui.card title="Repay" subtitle="Repay fee first, then principal. Full repayment releases collateral.">
                        <form method="POST" action="{{ route('credit.repay') }}" class="space-y-4">
                            @csrf
                            <x-ui.input :label="'Amount (' . $line['principalSymbol'] . ')'" name="repayAmount" :value="old('repayAmount')"
                                type="text" inputmode="decimal" placeholder="0.00" :error="$errors->first('repayAmount')"
                                :hint="'Outstanding debt: ' . $line['debt']" />
                            <x-ui.button type="submit" variant="secondary" class="w-full" icon="arrow-up-tray">Repay</x-ui.button>
                        </form>
                    </x-ui.card>
                </div>

                {{-- Activity --}}
                <x-ui.card title="Activity" subtitle="Draws and repayments on this credit line.">
                    @if (count($line['transactions']))
                        <x-ui.table :headers="['Type', 'Amount', 'When']">
                            @foreach ($line['transactions'] as $tx)
                                <tr class="hover:bg-neutral-50">
                                    <td class="px-4 py-3">
                                        <span @class([
                                            'rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-amber-50 text-amber-600' => $tx['type'] === 'draw',
                                            'bg-emerald-50 text-emerald-600' => $tx['type'] !== 'draw',
                                        ])>{{ ucfirst($tx['type']) }}</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-2">
                                            <span class="grid h-6 w-6 place-items-center rounded-full bg-brand-50 text-[9px] font-bold text-brand-600">{{ \Illuminate\Support\Str::substr($tx['symbol'], 0, 2) }}</span>
                                            <span class="tabular text-sm font-semibold text-neutral-900">{{ $tx['amount'] }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $tx['at_human'] }}</td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    @else
                        <x-ui.empty-state icon="clock" title="No activity yet" description="Draws and repayments will appear here." />
                    @endif
                </x-ui.card>
            </div>
        @else
            {{-- Open a credit line --}}
            <div class="grid gap-6 lg:grid-cols-5">
                <div class="lg:col-span-3">
                    <x-ui.card title="Open a credit line" subtitle="Pledge crypto collateral and borrow stablecoins against it.">
                        @if ($fundedCrypto->isEmpty())
                            <x-ui.empty-state icon="banknotes" title="No crypto to pledge"
                                description="You need a funded crypto balance to open a credit line.">
                                <x-slot:action>
                                    <x-ui.button href="{{ route('deposit') }}" icon="arrow-down-tray">Make a deposit</x-ui.button>
                                </x-slot:action>
                            </x-ui.empty-state>
                        @else
                            <form method="POST" action="{{ route('credit.open') }}" class="space-y-5">
                                @csrf
                                <div>
                                    <x-ui.select label="Collateral asset" name="collateralAssetId" :error="$errors->first('collateralAssetId')">
                                        @foreach ($fundedCrypto as $w)
                                            <option value="{{ $w['assetId'] }}" @selected(old('collateralAssetId') == $w['assetId'])>{{ $w['symbol'] }} — {{ $w['available'] }} available</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>

                                <x-ui.input label="Collateral amount" name="collateralAmount" :value="old('collateralAmount')" type="text"
                                    inputmode="decimal" placeholder="0.00" :error="$errors->first('collateralAmount')" />

                                <div>
                                    <x-ui.select label="Borrow asset" name="principalAssetId" :error="$errors->first('principalAssetId')">
                                        @foreach ($principalAssets as $a)
                                            <option value="{{ $a['id'] }}" @selected(old('principalAssetId', $principalAssets->firstWhere('symbol', 'USDT')['id'] ?? null) == $a['id'])>{{ $a['symbol'] }} — {{ $a['name'] }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>

                                <x-ui.button type="submit" class="w-full" icon="lock-closed">Pledge collateral &amp; open line</x-ui.button>
                            </form>
                        @endif
                    </x-ui.card>
                </div>

                <div class="lg:col-span-2">
                    <x-ui.card title="How it works">
                        <ol class="space-y-4 text-sm text-neutral-600">
                            <li class="flex gap-3">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600">1</span>
                                Pledge crypto as collateral. It's locked, not sold — you keep the upside.
                            </li>
                            <li class="flex gap-3">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600">2</span>
                                Draw stablecoins up to your maximum loan-to-value.
                            </li>
                            <li class="flex gap-3">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-600">3</span>
                                Repay any time. Clearing the debt releases your collateral automatically.
                            </li>
                        </ol>
                    </x-ui.card>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
