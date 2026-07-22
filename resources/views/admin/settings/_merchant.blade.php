<x-admin.form-layout title="Merchant" description="Merchant onboarding, fees and invoicing." class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'merchant') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="merchant_enabled" label="Enable Merchant Payments" class="w-full">
            <x-admin.input.boolean name="merchant_enabled" :value="old('merchant_enabled', getSetting('merchant_enabled', true))" />
        </x-admin.input.group>

        <x-admin.input.group id="merchant_fee_bps" label="Processing Fee (bps)" required class="w-full" hints="Default fee on each merchant payment. 100 bps = 1%. Per-merchant overrides still apply.">
            <x-admin.input type="number" min="0" max="10000" name="merchant_fee_bps" :value="old('merchant_fee_bps', getSetting('merchant_fee_bps', 100))" required />
        </x-admin.input.group>

        <x-admin.input.group id="merchant_invoice_ttl_minutes" label="Invoice Expiry (minutes)" required class="w-full">
            <x-admin.input type="number" min="1" max="10080" name="merchant_invoice_ttl_minutes" :value="old('merchant_invoice_ttl_minutes', getSetting('merchant_invoice_ttl_minutes', 60))" required />
        </x-admin.input.group>

        <x-admin.input.group id="merchant_auto_approve" label="Auto-approve Applications" class="w-full" hints="Off = new merchants land in the review queue.">
            <x-admin.input.boolean name="merchant_auto_approve" :value="old('merchant_auto_approve', getSetting('merchant_auto_approve', true))" />
        </x-admin.input.group>

        <x-admin.input.group id="merchant_allow_refunds" label="Allow Merchant Refunds" class="w-full">
            <x-admin.input.boolean name="merchant_allow_refunds" :value="old('merchant_allow_refunds', getSetting('merchant_allow_refunds', true))" />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
