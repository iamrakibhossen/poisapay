<x-admin.form-layout title="Compliance / AML" description="Screening, alert thresholds and watchlists." class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'compliance') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="aml_screening_enabled" label="Sanctions Screening" class="w-full" hints="Screen users at onboarding and withdrawal.">
            <x-admin.input.boolean name="aml_screening_enabled" :value="old('aml_screening_enabled', getSetting('aml_screening_enabled', true))" />
        </x-admin.input.group>

        <x-admin.input.group id="aml_auto_open_case" label="Auto-open Cases" class="w-full" hints="Automatically open a compliance case when an alert is raised.">
            <x-admin.input.boolean name="aml_auto_open_case" :value="old('aml_auto_open_case', getSetting('aml_auto_open_case', true))" />
        </x-admin.input.group>

        <x-admin.input.group id="aml_large_amount_minor" label="Large-amount Threshold (minor units)" required class="w-full" hints="Withdrawals above this raise a large-amount alert.">
            <x-admin.input type="number" min="0" name="aml_large_amount_minor" :value="old('aml_large_amount_minor', getSetting('aml_large_amount_minor', 100000))" required />
        </x-admin.input.group>

        <x-admin.input.group id="aml_velocity_window_hours" label="Velocity Window (hours)" required class="w-full">
            <x-admin.input type="number" min="1" max="168" name="aml_velocity_window_hours" :value="old('aml_velocity_window_hours', getSetting('aml_velocity_window_hours', 24))" required />
        </x-admin.input.group>

        <x-admin.input.group id="aml_sanctions_denylist" label="Sanctions Denylist" class="w-full" hints="Comma-separated names. A match forces a critical hit.">
            <x-admin.input.textarea name="aml_sanctions_denylist" :value="implode(', ', (array) old('aml_sanctions_denylist', getSetting('aml_sanctions_denylist', [])))" rows="2" />
        </x-admin.input.group>

        <x-admin.input.group id="aml_watchlist" label="Watchlist" class="w-full" hints="Comma-separated names. A match flags for review.">
            <x-admin.input.textarea name="aml_watchlist" :value="implode(', ', (array) old('aml_watchlist', getSetting('aml_watchlist', [])))" rows="2" />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
