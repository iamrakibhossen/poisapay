<x-admin.form-layout title="Exchange" description="Swap pricing and pair restrictions." class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'exchange') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="exchange_enabled" label="Enable Exchange" class="w-full">
            <x-admin.input.boolean name="exchange_enabled" :value="old('exchange_enabled', getSetting('exchange_enabled', true))" />
        </x-admin.input.group>

        <x-admin.input.group id="exchange_spread_bps" label="Exchange Spread (bps)" required class="w-full" hints="Default spread applied to every swap. 100 bps = 1%. Per-pair overrides still apply.">
            <x-admin.input type="number" min="0" max="10000" name="exchange_spread_bps"
                :value="old('exchange_spread_bps', getSetting('exchange_spread_bps', 75))" required />
        </x-admin.input.group>

        <x-admin.input.group id="exchange_restrict_pairs" label="Restrict to Configured Pairs" class="w-full"
            hints="When on, users can only swap between explicitly configured trading pairs.">
            <x-admin.input.boolean name="exchange_restrict_pairs" :value="old('exchange_restrict_pairs', getSetting('exchange_restrict_pairs', false))" />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
