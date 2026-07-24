<x-admin.form-layout :title="__('Risk Scoring')" :description="__('Withdrawal auto-approve risk model. Weighted signals sum to a score; the score bands decide the risk level and whether manual review is required.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'risk') }}">
        @csrf
        @method('PUT')

        <h3 class="text-sm font-semibold text-gray-700">{{ __('Signal Weights (points)') }}</h3>

        <x-admin.input.group id="risk_weight_large_amount" :label="__('Amount above auto-approve limit')" required class="w-full">
            <x-admin.input type="number" min="0" max="100" name="risk_weight_large_amount"
                :value="old('risk_weight_large_amount', getSetting('risk_weight_large_amount', 40))" required />
        </x-admin.input.group>

        <x-admin.input.group id="risk_weight_velocity" :label="__('High withdrawal velocity')" required class="w-full">
            <x-admin.input type="number" min="0" max="100" name="risk_weight_velocity"
                :value="old('risk_weight_velocity', getSetting('risk_weight_velocity', 25))" required />
        </x-admin.input.group>

        <x-admin.input.group id="risk_weight_new_account" :label="__('Fresh account')" required class="w-full">
            <x-admin.input type="number" min="0" max="100" name="risk_weight_new_account"
                :value="old('risk_weight_new_account', getSetting('risk_weight_new_account', 20))" required />
        </x-admin.input.group>

        <x-admin.input.group id="risk_weight_new_destination" :label="__('New destination address')" required class="w-full">
            <x-admin.input type="number" min="0" max="100" name="risk_weight_new_destination"
                :value="old('risk_weight_new_destination', getSetting('risk_weight_new_destination', 10))" required />
        </x-admin.input.group>

        <h3 class="pt-2 text-sm font-semibold text-gray-700">{{ __('Thresholds') }}</h3>

        <x-admin.input.group id="risk_velocity_count" :label="__('Velocity count (withdrawals)')" required class="w-full"
            :hints="__('Withdrawals within the window that trigger the velocity signal.')">
            <x-admin.input type="number" min="1" max="1000" name="risk_velocity_count"
                :value="old('risk_velocity_count', getSetting('risk_velocity_count', 5))" required />
        </x-admin.input.group>

        <x-admin.input.group id="risk_velocity_window_hours" :label="__('Velocity window (hours)')" required class="w-full">
            <x-admin.input type="number" min="1" max="168" name="risk_velocity_window_hours"
                :value="old('risk_velocity_window_hours', getSetting('risk_velocity_window_hours', 24))" required />
        </x-admin.input.group>

        <x-admin.input.group id="risk_fresh_account_hours" :label="__('Fresh account window (hours)')" required class="w-full">
            <x-admin.input type="number" min="0" max="8760" name="risk_fresh_account_hours"
                :value="old('risk_fresh_account_hours', getSetting('risk_fresh_account_hours', 24))" required />
        </x-admin.input.group>

        <h3 class="pt-2 text-sm font-semibold text-gray-700">{{ __('Score Bands (minimum score for each level)') }}</h3>

        <x-admin.input.group id="risk_band_critical" :label="__('Critical ≥')" required class="w-full">
            <x-admin.input type="number" min="0" max="100" name="risk_band_critical"
                :value="old('risk_band_critical', getSetting('risk_band_critical', 80))" required />
        </x-admin.input.group>

        <x-admin.input.group id="risk_band_high" :label="__('High ≥')" required class="w-full">
            <x-admin.input type="number" min="0" max="100" name="risk_band_high"
                :value="old('risk_band_high', getSetting('risk_band_high', 50))" required />
        </x-admin.input.group>

        <x-admin.input.group id="risk_band_medium" :label="__('Medium ≥')" required class="w-full"
            :hints="__('Scores below this are Low. Above Low forces manual review.')">
            <x-admin.input type="number" min="0" max="100" name="risk_band_medium"
                :value="old('risk_band_medium', getSetting('risk_band_medium', 25))" required />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
