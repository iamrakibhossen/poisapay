<x-layouts.admin :title="'Simulation'">
    <div class="space-y-6">
        <x-ui.page-header title="Simulation" subtitle="Stand-in for the Blockchain Monitor & Signer — advance chain state without a live network." />

        <x-ui.alert type="info" title="Development harness">
            In production the Blockchain Monitor detects inbound deposits and advances confirmations, and the isolated
            Signer settles approved withdrawals. Here you drive that pipeline manually to exercise the ledger end-to-end.
        </x-ui.alert>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Chain tick --}}
            <x-ui.card title="Run chain tick" subtitle="Advance confirmations, credit deposits, and settle approved withdrawals.">
                <p class="mb-5 text-sm text-neutral-500">
                    Runs <span class="font-mono text-xs text-neutral-700">poisapay:chain-tick</span>,
                    mirroring one pass of the monitor: detected deposits gain confirmations and, once confirmed, are
                    credited; approved withdrawals are signed and settled.
                </p>
                <form method="POST" action="{{ route('admin.simulation.tick') }}"
                    onsubmit="return confirm('Advance simulated chain state now?')">
                    @csrf
                    <x-ui.button type="submit" size="lg" icon="forward">Run chain tick</x-ui.button>
                </form>
            </x-ui.card>

            {{-- Simulate deposit --}}
            <x-ui.card title="Simulate deposit" subtitle="Detect an inbound deposit to a user's custodial address.">
                @if ($assets->isEmpty())
                    <x-ui.empty-state icon="cube" title="No crypto assets"
                        description="Configure crypto assets on a chain before simulating deposits." />
                @else
                    <form method="POST" action="{{ route('admin.simulation.deposit') }}" class="space-y-4">
                        @csrf
                        <x-ui.input label="User email" name="userEmail" :value="old('userEmail')" icon="user"
                            placeholder="customer@example.com" :error="$errors->first('userEmail')" />

                        <x-ui.select label="Asset" name="assetId" :error="$errors->first('assetId')">
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}" @selected((int) old('assetId', $defaultAssetId) === $asset->id)>{{ $asset->symbol }} — {{ $asset->name }}{{ $asset->chain ? ' ('.$asset->chain->name.')' : '' }}</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.input label="Amount" name="amount" :value="old('amount')" type="text" inputmode="decimal"
                            placeholder="0.00" :error="$errors->first('amount')" />

                        <x-ui.button type="submit" icon="arrow-down-tray">Simulate deposit</x-ui.button>
                        <p class="text-xs text-neutral-500">
                            Records a detected deposit at 0 confirmations. Run a chain tick to confirm and credit it.
                        </p>
                    </form>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
