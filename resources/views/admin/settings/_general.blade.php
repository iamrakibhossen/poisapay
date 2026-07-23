<x-admin.form-layout :title="__('General Settings')" :description="__('Core identity and operational state of the platform.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'general') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="site_name" :label="__('Site Name')" required class="w-full">
            <x-admin.input name="site_name" :value="old('site_name', getSetting('site_name', 'PoisaPay'))" required />
        </x-admin.input.group>

        <x-admin.input.group id="site_slogan" :label="__('Slogan')" class="w-full">
            <x-admin.input name="site_slogan" :value="old('site_slogan', getSetting('site_slogan'))" />
        </x-admin.input.group>

        <x-admin.input.group id="base_currency" :label="__('Base Currency')" required class="w-full"
            :hints="__('The currency balances, reports and limits are denominated in.')">
            <x-admin.input.select name="base_currency">
                @php $fiats = \App\Models\Asset::where('kind', 'fiat')->whereNotNull('currency_code')->pluck('currency_code')->unique(); @endphp
                @forelse ($fiats as $symbol)
                    <option value="{{ $symbol }}" @selected(old('base_currency', getSetting('base_currency', 'BDT')) === $symbol)>{{ $symbol }}</option>
                @empty
                    <option value="{{ getSetting('base_currency', 'BDT') }}">{{ getSetting('base_currency', 'BDT') }}</option>
                @endforelse
            </x-admin.input.select>
        </x-admin.input.group>

        <x-admin.input.group id="support_email" :label="__('Support Email')" required class="w-full">
            <x-admin.input type="email" name="support_email" :value="old('support_email', getSetting('support_email'))" required />
        </x-admin.input.group>

        <x-admin.input.group id="maintenance_mode" :label="__('Maintenance Mode')" class="w-full"
            :hints="__('Takes the platform offline for non-admins.')">
            <x-admin.input.boolean name="maintenance_mode" :value="old('maintenance_mode', getSetting('maintenance_mode', false))" />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
