<x-admin.form-layout :title="__('Branding')" :description="__('Colours and logos applied across the app.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'branding') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="primary_color" :label="__('Primary Colour')" required class="w-full">
            <div class="flex items-center gap-3">
                <input type="color" id="primary_color" name="primary_color"
                    value="{{ old('primary_color', getSetting('primary_color', '#FFC107')) }}"
                    class="h-11 w-16 cursor-pointer rounded-lg border border-gray-300" />
                <span class="font-mono text-sm text-gray-500">{{ old('primary_color', getSetting('primary_color', '#FFC107')) }}</span>
            </div>
            <x-admin.input-error for="primary_color" class="mt-1.5" />
        </x-admin.input.group>

        <x-admin.input.group id="secondary_color" :label="__('Secondary Colour')" required class="w-full">
            <div class="flex items-center gap-3">
                <input type="color" id="secondary_color" name="secondary_color"
                    value="{{ old('secondary_color', getSetting('secondary_color', '#1F2937')) }}"
                    class="h-11 w-16 cursor-pointer rounded-lg border border-gray-300" />
                <span class="font-mono text-sm text-gray-500">{{ old('secondary_color', getSetting('secondary_color', '#1F2937')) }}</span>
            </div>
            <x-admin.input-error for="secondary_color" class="mt-1.5" />
        </x-admin.input.group>

        <x-admin.input.group id="site_logo" :label="__('Logo URL')" class="w-full">
            <x-admin.input name="site_logo" placeholder="https://…" :value="old('site_logo', getSetting('site_logo'))" />
        </x-admin.input.group>

        <x-admin.input.group id="site_favicon" :label="__('Favicon URL')" class="w-full">
            <x-admin.input name="site_favicon" placeholder="https://…" :value="old('site_favicon', getSetting('site_favicon'))" />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
