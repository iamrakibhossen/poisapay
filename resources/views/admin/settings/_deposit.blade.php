<x-admin.form-layout :title="__('Deposit')" :description="__('Deposit availability and rules.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'deposit') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="deposit_enabled" :label="__('Enable Deposits')" class="w-full" :hints="__('Turn all deposits (crypto + manual) on or off.')">
            <x-admin.input.boolean name="deposit_enabled" :value="old('deposit_enabled', getSetting('deposit_enabled', true))" />
        </x-admin.input.group>

        <x-admin.input.group id="deposit_fee_percent" :label="__('Deposit Fee (%)')" required class="w-full"
            :hints="__('Platform fee taken from every deposit and booked to revenue. The user is credited the amount minus this fee.')">
            <x-admin.input type="number" step="0.01" min="0" max="100" name="deposit_fee_percent"
                :value="old('deposit_fee_percent', getSetting('deposit_fee_percent', '1'))" required />
        </x-admin.input.group>

        <p class="text-sm text-gray-500">{{ __('Deposit methods (banks, mobile wallets, networks) are managed under') }}
            <a href="{{ route('admin.deposit-methods') }}" class="font-medium text-brand-600 hover:underline">{{ __('Deposit Methods') }}</a>.</p>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
