<x-admin.form-layout title="Cards" description="Defaults and fees for the card program." class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'cards') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="cards_enabled" label="Enable Cards" class="w-full">
            <x-admin.input.boolean name="cards_enabled" :value="old('cards_enabled', getSetting('cards_enabled', true))" />
        </x-admin.input.group>

        <x-admin.input.group id="card_fee_bps" label="Card Fee (bps)" required class="w-full" hints="100 bps = 1% on each settled card transaction.">
            <x-admin.input type="number" min="0" max="10000" name="card_fee_bps" :value="old('card_fee_bps', getSetting('card_fee_bps', 100))" required />
        </x-admin.input.group>

        <x-admin.input.group id="card_default_daily_limit" label="Default Daily Limit" required class="w-full" hints="Settlement-currency minor units (e.g. 500000 = $5,000.00).">
            <x-admin.input type="number" min="0" name="card_default_daily_limit" :value="old('card_default_daily_limit', getSetting('card_default_daily_limit', 500000))" required />
        </x-admin.input.group>

        <x-admin.input.group id="card_default_per_tx_limit" label="Default Per-transaction Limit" required class="w-full" hints="Settlement-currency minor units.">
            <x-admin.input type="number" min="0" name="card_default_per_tx_limit" :value="old('card_default_per_tx_limit', getSetting('card_default_per_tx_limit', 200000))" required />
        </x-admin.input.group>

        <x-admin.input.group id="card_dispute_window_days" label="Dispute Window (days)" required class="w-full">
            <x-admin.input type="number" min="0" max="365" name="card_dispute_window_days" :value="old('card_dispute_window_days', getSetting('card_dispute_window_days', 60))" required />
        </x-admin.input.group>

        <x-admin.input.group id="card_allow_physical" label="Allow Physical Cards" class="w-full">
            <x-admin.input.boolean name="card_allow_physical" :value="old('card_allow_physical', getSetting('card_allow_physical', true))" />
        </x-admin.input.group>

        <x-admin.input.group id="card_reveal_enabled" label="Allow Card Detail Reveal" class="w-full" hints="Let users reveal card details via the PCI-compliant iframe.">
            <x-admin.input.boolean name="card_reveal_enabled" :value="old('card_reveal_enabled', getSetting('card_reveal_enabled', true))" />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
