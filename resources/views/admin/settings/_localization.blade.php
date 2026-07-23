<x-admin.form-layout :title="__('Localization')" :description="__('Default language and the locales users can choose from.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'localization') }}">
        @csrf
        @method('PUT')

        @php $locales = (array) getSetting('available_locales', ['en']); @endphp

        <x-admin.input.group id="default_locale" :label="__('Default Locale')" required class="w-full" :hints="__('The language new users get by default (e.g. en, bn).')">
            <x-admin.input.select name="default_locale">
                @foreach ($locales as $loc)
                    <option value="{{ $loc }}" @selected(old('default_locale', getSetting('default_locale', 'en')) === $loc)>{{ $loc }}</option>
                @endforeach
            </x-admin.input.select>
        </x-admin.input.group>

        <x-admin.input.group id="available_locales" :label="__('Available Locales')" class="w-full" :hints="__('Comma-separated locale codes users can switch between (e.g. en, bn).')">
            <x-admin.input name="available_locales" :value="implode(', ', (array) old('available_locales', $locales))" />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
