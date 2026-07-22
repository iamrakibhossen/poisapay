<x-admin.form-layout title="Announcement" description="A banner shown across the top of the app." class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'announcement') }}">
        @csrf
        @method('PUT')

        <x-admin.input.group id="header_announcement_enabled" label="Show Header Announcement" class="w-full">
            <x-admin.input.boolean name="header_announcement_enabled"
                :value="old('header_announcement_enabled', getSetting('header_announcement_enabled', false))" />
        </x-admin.input.group>

        <x-admin.input.group id="header_announcement_text" label="Announcement Text" class="w-full">
            <x-admin.input name="header_announcement_text" :value="old('header_announcement_text', getSetting('header_announcement_text'))" />
        </x-admin.input.group>

        <x-admin.input.group id="header_announcement_link" label="Link (optional)" class="w-full">
            <x-admin.input name="header_announcement_link" placeholder="https://…" :value="old('header_announcement_link', getSetting('header_announcement_link'))" />
        </x-admin.input.group>

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
