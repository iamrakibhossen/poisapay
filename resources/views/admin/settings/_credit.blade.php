<x-admin.form-layout title="Credit" description="Crypto-backed credit lines." class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'credit') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="credit_enabled" label="Enable Credit" class="w-full" hints="Allow users to borrow against their crypto collateral.">
            <x-admin.input.boolean name="credit_enabled" :value="old('credit_enabled', getSetting('credit_enabled', true))" />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
