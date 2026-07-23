<x-admin.form-layout :title="__('Rewards')" :description="__('Rewards and referral programme.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'rewards') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="rewards_enabled" :label="__('Enable Rewards')" class="w-full">
            <x-admin.input.boolean name="rewards_enabled" :value="old('rewards_enabled', getSetting('rewards_enabled', true))" />
        </x-admin.input.group>

        <x-admin.input.group id="referral_enabled" :label="__('Enable Referrals')" class="w-full">
            <x-admin.input.boolean name="referral_enabled" :value="old('referral_enabled', getSetting('referral_enabled', true))" />
        </x-admin.input.group>

        <p class="text-sm text-gray-500">{{ __('Reward amounts and campaigns are managed under') }}
            <a href="{{ route('admin.rewards') }}" class="font-medium text-brand-600 hover:underline">{{ __('Rewards') }}</a>.</p>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
