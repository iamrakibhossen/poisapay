<x-admin.form-layout :title="__('Sell')" :description="__('Marketplace commission tiers, refunds, payouts and digital delivery.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'sell') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="sell_enabled" :label="__('Enable Sell')" class="w-full" :hints="__('Off = the storefront, checkout and seller onboarding are all closed.')">
            <x-admin.input.boolean name="sell_enabled" :value="old('sell_enabled', getSetting('sell_enabled', false))" />
        </x-admin.input.group>

        <div class="rounded-lg border border-gray-200 p-4">
            <p class="mb-3 text-sm font-semibold text-gray-900">{{ __('Commission by plan (bps)') }}</p>
            <p class="mb-4 text-xs text-gray-500">{{ __('Platform cut on each sale. 100 bps = 1%. A per-seller override, if set, still wins over the plan rate.') }}</p>
            <div class="grid gap-4 sm:grid-cols-3">
                <x-admin.input.group id="sell_commission_bps_free" :label="__('Free')" required class="w-full">
                    <x-admin.input type="number" min="0" max="10000" name="sell_commission_bps_free" :value="old('sell_commission_bps_free', getSetting('sell_commission_bps_free', 1000))" required />
                </x-admin.input.group>

                <x-admin.input.group id="sell_commission_bps_pro" :label="__('Pro')" required class="w-full">
                    <x-admin.input type="number" min="0" max="10000" name="sell_commission_bps_pro" :value="old('sell_commission_bps_pro', getSetting('sell_commission_bps_pro', 700))" required />
                </x-admin.input.group>

                <x-admin.input.group id="sell_commission_bps_business" :label="__('Business')" required class="w-full">
                    <x-admin.input type="number" min="0" max="10000" name="sell_commission_bps_business" :value="old('sell_commission_bps_business', getSetting('sell_commission_bps_business', 500))" required />
                </x-admin.input.group>
            </div>
        </div>

        <x-admin.input.group id="sell_refund_window_days" :label="__('Refund Window (days)')" required class="w-full" :hints="__('How long after purchase a buyer stays eligible for a refund. Held earnings release once this passes.')">
            <x-admin.input type="number" min="0" max="365" name="sell_refund_window_days" :value="old('sell_refund_window_days', getSetting('sell_refund_window_days', 14))" required />
        </x-admin.input.group>

        <x-admin.input.group id="sell_earnings_hold" :label="__('Hold Seller Earnings')" class="w-full" :hints="__('On = seller payouts are held (locked) until the refund window closes, then auto-released.')">
            <x-admin.input.boolean name="sell_earnings_hold" :value="old('sell_earnings_hold', getSetting('sell_earnings_hold', false))" />
        </x-admin.input.group>

        <x-admin.input.group id="sell_download_limit" :label="__('Download Limit (per file)')" required class="w-full" :hints="__('Max times a buyer can download each digital file.')">
            <x-admin.input type="number" min="1" max="100" name="sell_download_limit" :value="old('sell_download_limit', getSetting('sell_download_limit', 5))" required />
        </x-admin.input.group>

        <x-admin.input.group id="sell_download_ttl_days" :label="__('Download Expiry (days)')" required class="w-full" :hints="__('How long download links stay valid after purchase.')">
            <x-admin.input type="number" min="1" max="365" name="sell_download_ttl_days" :value="old('sell_download_ttl_days', getSetting('sell_download_ttl_days', 30))" required />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
