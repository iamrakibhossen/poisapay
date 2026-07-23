<x-admin.form-layout :title="__('P2P Marketplace')" :description="__('Peer-to-peer USDT trading — escrow, fees and eligibility.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'p2p') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="p2p_enabled" :label="__('Enable P2P Marketplace')" class="w-full" :hints="__('Off = the entire P2P surface (marketplace, ads, orders, chat) is hidden and returns 404.')">
            <x-admin.input.boolean name="p2p_enabled" :value="old('p2p_enabled', getSetting('p2p_enabled', false))" />
        </x-admin.input.group>

        <x-admin.input.group id="p2p_taker_fee_bps" :label="__('Taker Fee (bps)')" required class="w-full" :hints="__('Platform fee taken from the escrowed crypto on release, credited to p2p:fee_income. 100 bps = 1%. 0 = no fee.')">
            <x-admin.input type="number" min="0" max="10000" name="p2p_taker_fee_bps" :value="old('p2p_taker_fee_bps', getSetting('p2p_taker_fee_bps', (int) config('p2p.taker_fee_bps', 0)))" required />
        </x-admin.input.group>

        <x-admin.input.group id="p2p_order_expiry_minutes" :label="__('Default Payment Window (minutes)')" required class="w-full" :hints="__('Default for new ads; each ad can override. Orders auto-cancel and refund the seller when the window elapses.')">
            <x-admin.input type="number" min="5" max="180" name="p2p_order_expiry_minutes" :value="old('p2p_order_expiry_minutes', getSetting('p2p_order_expiry_minutes', (int) config('p2p.order_expiry_minutes', 15)))" required />
        </x-admin.input.group>

        <x-admin.input.group id="p2p_require_full_kyc" :label="__('Require Full KYC to Trade')" class="w-full" :hints="__('On = both parties must be fully verified. Off = Basic tier is enough.')">
            <x-admin.input.boolean name="p2p_require_full_kyc" :value="old('p2p_require_full_kyc', getSetting('p2p_require_full_kyc', false))" />
        </x-admin.input.group>

        <div class="border-t border-gray-200 pt-5">
            <p class="text-sm font-semibold text-neutral-900">{{ __('Risk & limits') }}</p>
            <p class="text-xs text-neutral-500">{{ __('Hard limits block a trade before escrow; high-value trades raise an AML alert.') }}</p>
        </div>

        <x-admin.input.group id="p2p_risk_enabled" :label="__('Enable Risk Engine')" class="w-full" :hints="__('Off = only sanctions/denylist checks run (limits and AML flags are skipped).')">
            <x-admin.input.boolean name="p2p_risk_enabled" :value="old('p2p_risk_enabled', getSetting('p2p_risk_enabled', true))" />
        </x-admin.input.group>

        <x-admin.input.group id="p2p_daily_limit_basic" :label="__('Daily Volume Cap — Basic tier (USDT)')" required class="w-full" :hints="__('Max USDT a Basic-tier user can trade per day. 0 = unlimited.')">
            <x-admin.input type="number" min="0" name="p2p_daily_limit_basic" :value="old('p2p_daily_limit_basic', getSetting('p2p_daily_limit_basic', 1000))" required />
        </x-admin.input.group>

        <x-admin.input.group id="p2p_daily_limit_full" :label="__('Daily Volume Cap — Full tier (USDT)')" required class="w-full" :hints="__('Max USDT a fully-verified user can trade per day. 0 = unlimited.')">
            <x-admin.input type="number" min="0" name="p2p_daily_limit_full" :value="old('p2p_daily_limit_full', getSetting('p2p_daily_limit_full', 25000))" required />
        </x-admin.input.group>

        <x-admin.input.group id="p2p_max_orders_per_hour" :label="__('Max Orders per Hour')" required class="w-full" :hints="__('Velocity cap per user. 0 = unlimited.')">
            <x-admin.input type="number" min="0" max="1000" name="p2p_max_orders_per_hour" :value="old('p2p_max_orders_per_hour', getSetting('p2p_max_orders_per_hour', 20))" required />
        </x-admin.input.group>

        <x-admin.input.group id="p2p_high_value_usdt" :label="__('High-value Alert Threshold (USDT)')" required class="w-full" :hints="__('Single trades at or above this raise an AML alert (not blocked). 0 = disabled.')">
            <x-admin.input type="number" min="0" name="p2p_high_value_usdt" :value="old('p2p_high_value_usdt', getSetting('p2p_high_value_usdt', 5000))" required />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
