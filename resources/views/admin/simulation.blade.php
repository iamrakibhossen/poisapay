<x-layouts.admin :title="__('Simulation')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Simulation')" :subtitle="__('Stand-in for the Blockchain Monitor & Signer — advance chain state without a live network.')" />

        <x-ui.alert type="info" :title="__('Development harness')">
            {{ __('In production the Blockchain Monitor detects inbound deposits and advances confirmations, and the isolated Signer settles approved withdrawals. Here you drive that pipeline manually to exercise the ledger end-to-end.') }}
        </x-ui.alert>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Chain tick --}}
            <x-ui.card :title="__('Run chain tick')" :subtitle="__('Advance confirmations, credit deposits, and settle approved withdrawals.')">
                <p class="mb-5 text-sm text-neutral-500">
                    {{ __('Runs') }} <span class="font-mono text-xs text-neutral-700">poisapay:chain-tick</span>,
                    {{ __('mirroring one pass of the monitor: detected deposits gain confirmations and, once confirmed, are credited; approved withdrawals are signed and settled.') }}
                </p>
                <form method="POST" action="{{ route('admin.simulation.tick') }}"
                    onsubmit="return confirm('{{ __('Advance simulated chain state now?') }}')">
                    @csrf
                    <x-ui.button type="submit" size="lg" icon="forward">{{ __('Run chain tick') }}</x-ui.button>
                </form>
            </x-ui.card>

            {{-- Simulate deposit --}}
            <x-ui.card :title="__('Simulate deposit')" :subtitle="__('Detect an inbound deposit to a user\'s custodial address.')">
                @if ($assets->isEmpty())
                    <x-ui.empty-state icon="cube" :title="__('No crypto assets')"
                        :description="__('Configure crypto assets on a chain before simulating deposits.')" />
                @else
                    <form method="POST" action="{{ route('admin.simulation.deposit') }}" class="space-y-4">
                        @csrf
                        <x-ui.input :label="__('User email')" name="userEmail" :value="old('userEmail')" icon="user"
                            :placeholder="__('customer@example.com')" :error="$errors->first('userEmail')" />

                        <x-ui.select :label="__('Asset')" name="assetId" :error="$errors->first('assetId')">
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}" @selected((int) old('assetId', $defaultAssetId) === $asset->id)>{{ $asset->symbol }} — {{ $asset->name }}{{ $asset->chain ? ' ('.$asset->chain->name.')' : '' }}</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.input :label="__('Amount')" name="amount" :value="old('amount')" type="text" inputmode="decimal"
                            placeholder="0.00" :error="$errors->first('amount')" />

                        <x-ui.button type="submit" icon="arrow-down-tray">{{ __('Simulate deposit') }}</x-ui.button>
                        <p class="text-xs text-neutral-500">
                            {{ __('Records a detected deposit at 0 confirmations. Run a chain tick to confirm and credit it.') }}
                        </p>
                    </form>
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.admin>
