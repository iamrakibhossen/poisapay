<x-admin.form-layout :title="__('KYC & Limits')" :description="__('Per-tier withdrawal ceilings and card-issuing eligibility. Ceilings are rolling-24h USD minor units (0 = unlimited).')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'kyc') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="kyc_basic_daily_withdrawal_ceiling" :label="__('Basic Tier — Daily Withdrawal Ceiling')" required class="w-full"
            :hints="__('USD minor units; e.g. 100000 = $1,000.00. 0 = unlimited.')">
            <x-admin.input type="number" min="0" name="kyc_basic_daily_withdrawal_ceiling"
                :value="old('kyc_basic_daily_withdrawal_ceiling', getSetting('kyc_basic_daily_withdrawal_ceiling', 100000))" required />
        </x-admin.input.group>

        <x-admin.input.group id="kyc_full_daily_withdrawal_ceiling" :label="__('Full Tier — Daily Withdrawal Ceiling')" required class="w-full"
            :hints="__('USD minor units; e.g. 2500000 = $25,000.00. 0 = unlimited.')">
            <x-admin.input type="number" min="0" name="kyc_full_daily_withdrawal_ceiling"
                :value="old('kyc_full_daily_withdrawal_ceiling', getSetting('kyc_full_daily_withdrawal_ceiling', 2500000))" required />
        </x-admin.input.group>

        <x-admin.input.group id="kyc_basic_can_issue_card" :label="__('Basic Tier — Can Issue Cards')" class="w-full">
            <x-admin.input.boolean name="kyc_basic_can_issue_card" :value="old('kyc_basic_can_issue_card', getSetting('kyc_basic_can_issue_card', false))" />
        </x-admin.input.group>

        <x-admin.input.group id="kyc_full_can_issue_card" :label="__('Full Tier — Can Issue Cards')" class="w-full">
            <x-admin.input.boolean name="kyc_full_can_issue_card" :value="old('kyc_full_can_issue_card', getSetting('kyc_full_can_issue_card', true))" />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
