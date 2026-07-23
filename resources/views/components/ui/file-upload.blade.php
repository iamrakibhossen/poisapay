@props([
    'label' => null,
    'name',
    'accept' => 'image/*',
    'error' => null,
    'optional' => false,
])

{{-- DollarHub file upload: same bordered control height as x-ui.input / x-ui.select,
     with a thumbnail + filename once chosen and a clear (✕) button. --}}
<div x-data="{
    fileName: '',
    preview: '',
    pick(e) {
        const f = e.target.files[0];
        this.fileName = f?.name || '';
        this.preview = f && f.type.startsWith('image/') ? URL.createObjectURL(f) : '';
    },
    clear() {
        this.fileName = '';
        this.preview = '';
        this.$refs.input.value = '';
    },
}">
    @if ($label)
        <label class="pp-label">{{ $label }}@if ($optional) <span class="font-normal text-neutral-400">{{ __('(optional)') }}</span>@endif</label>
    @endif

    <div @class([
        'relative flex min-h-[2.625rem] w-full items-center gap-2.5 rounded-lg border pr-2',
        'border-red-400 focus-within:border-red-500' => $error,
        'border-gray-300 hover:border-brand-500 focus-within:border-brand-500' => ! $error,
    ])>
        {{-- Clickable area opens the picker --}}
        <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2.5 py-2 pl-3">
            <input x-ref="input" type="file" name="{{ $name }}" accept="{{ $accept }}" {{ $attributes }} class="sr-only" x-on:change="pick($event)" />

            <img x-show="preview" x-cloak :src="preview" class="h-6 w-6 shrink-0 rounded object-cover" alt="" />
            <span x-show="!preview" class="grid h-6 w-6 shrink-0 place-items-center rounded bg-brand-50 text-brand-600">
                <x-heroicon-o-arrow-up-tray class="h-3.5 w-3.5" />
            </span>

            <span class="min-w-0 flex-1 truncate text-sm" :class="fileName ? 'font-medium text-gray-700' : 'text-gray-400'"
                x-text="fileName || 'Upload image'"></span>
        </label>

        {{-- Clear button, only once a file is chosen --}}
        <button type="button" x-show="fileName" x-cloak x-on:click="clear()" title="{{ __('Remove') }}"
            class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-gray-400 transition hover:bg-gray-100 hover:text-gray-600">
            <x-heroicon-o-x-mark class="h-4 w-4" />
        </button>
    </div>

    @if ($error)
        <p class="mt-1.5 text-xs text-rose-600">{{ $error }}</p>
    @endif
</div>
