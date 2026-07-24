<x-admin.form-layout :title="__('Withdrawal')" :description="__('Withdrawal availability, auto-approval and limits.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'withdrawal') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="withdrawal_enabled" :label="__('Enable Withdrawals')" class="w-full">
            <x-admin.input.boolean name="withdrawal_enabled" :value="old('withdrawal_enabled', getSetting('withdrawal_enabled', true))" />
        </x-admin.input.group>

        <x-admin.input.group id="withdrawal_fee_percent_coin" :label="__('Coin Withdrawal Fee (%)')" required class="w-full"
            :hints="__('Platform fee on crypto withdrawals (added on top of the network fee) and booked to revenue.')">
            <x-admin.input type="number" step="0.01" min="0" max="100" name="withdrawal_fee_percent_coin"
                :value="old('withdrawal_fee_percent_coin', \App\Domain\Fees\PlatformFees::withdrawalCoinPercent())" required />
        </x-admin.input.group>

        <x-admin.input.group id="withdrawal_fee_percent_fiat" :label="__('Fiat Withdrawal Fee (%)')" required class="w-full"
            :hints="__('Platform fee on fiat cash-outs (added on top of the payout-method fee) and booked to revenue.')">
            <x-admin.input type="number" step="0.01" min="0" max="100" name="withdrawal_fee_percent_fiat"
                :value="old('withdrawal_fee_percent_fiat', \App\Domain\Fees\PlatformFees::withdrawalFiatPercent())" required />
        </x-admin.input.group>

        <x-admin.input.group id="withdrawal_auto_approve_limit" :label="__('Auto-approve Limit')" required class="w-full"
            :hints="__('Minor units; withdrawals above this need manual review.')">
            <x-admin.input type="number" min="0" name="withdrawal_auto_approve_limit"
                :value="old('withdrawal_auto_approve_limit', getSetting('withdrawal_auto_approve_limit', 50000))" required />
        </x-admin.input.group>

        <x-admin.input.group id="min_withdrawal_usd" :label="__('Minimum Withdrawal (USD)')" required class="w-full">
            <x-admin.input type="number" min="0" name="min_withdrawal_usd"
                :value="old('min_withdrawal_usd', getSetting('min_withdrawal_usd', 1))" required />
        </x-admin.input.group>

        <x-admin.input.group id="daily_withdrawal_count" :label="__('Daily Withdrawal Count')" required class="w-full">
            <x-admin.input type="number" min="0" max="1000" name="daily_withdrawal_count"
                :value="old('daily_withdrawal_count', getSetting('daily_withdrawal_count', 10))" required />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
