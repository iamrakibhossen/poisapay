<x-admin.form-layout :title="__('Transfer')" :description="__('Peer-to-peer transfers between PoisaPay users.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'transfer') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="transfer_enabled" :label="__('Enable Transfers')" class="w-full" :hints="__('Allow users to send funds to each other.')">
            <x-admin.input.boolean name="transfer_enabled" :value="old('transfer_enabled', getSetting('transfer_enabled', true))" />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
