@props([
    'name' => 'avatar',            // file input name
    'removeName' => 'remove_avatar', // hidden checkbox toggled when clearing
    'current' => null,             // current image URL (null → show initials)
    'displayName' => '',           // used for the initials fallback
    'label' => null,
    'hint' => null,
    'error' => null,
])

@php
    $initials = collect(explode(' ', trim($displayName)))->filter()->take(2)
        ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('') ?: '?';
    $hue = crc32($displayName) % 360;
    $hint ??= __('JPEG, PNG or WebP · up to 2 MB');
@endphp

{{-- Profile-picture uploader: click the avatar (or "Change photo") to pick a file,
     with a live preview; "Remove" clears it back to initials on save. --}}
<div x-data="{
        preview: @js($current),
        removed: false,
        pick(e) {
            const f = e.target.files[0];
            if (!f) return;
            this.preview = URL.createObjectURL(f);
            this.removed = false;
        },
        remove() {
            this.preview = null;
            this.removed = true;
            this.$refs.input.value = '';
        },
    }" class="space-y-2">
    @if ($label)
        <span class="pp-label">{{ $label }}</span>
    @endif

    <div class="flex items-center gap-4">
        <button type="button" x-on:click="$refs.input.click()"
            class="group relative h-16 w-16 shrink-0 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
            <img x-show="preview" x-cloak :src="preview" alt="{{ $displayName }}"
                class="h-16 w-16 rounded-full object-cover" />
            <span x-show="!preview"
                class="grid h-16 w-16 place-items-center rounded-full text-lg font-semibold text-white"
                style="background: hsl({{ $hue }} 60% 45%);">{{ $initials }}</span>
            <span class="absolute inset-0 grid place-items-center rounded-full bg-black/40 text-white opacity-0 transition group-hover:opacity-100">
                <x-heroicon-o-camera class="h-5 w-5" />
            </span>
        </button>

        <div class="min-w-0 space-y-1">
            <div class="flex items-center gap-3 text-sm">
                <button type="button" x-on:click="$refs.input.click()"
                    class="font-medium text-brand-600 hover:text-brand-700">{{ __('Change photo') }}</button>
                <button type="button" x-show="preview" x-cloak x-on:click="remove()"
                    class="text-neutral-500 hover:text-rose-600">{{ __('Remove') }}</button>
            </div>
            <p class="text-xs text-neutral-400">{{ $hint }}</p>
        </div>
    </div>

    <input x-ref="input" type="file" name="{{ $name }}" accept="image/png,image/jpeg,image/webp"
        {{ $attributes }} class="sr-only" x-on:change="pick($event)" />
    {{-- Submits "1" only while removed is true (unchecked boxes aren't sent). --}}
    <input type="checkbox" x-model="removed" name="{{ $removeName }}" value="1" class="sr-only" tabindex="-1" aria-hidden="true" />

    @if ($error)
        <p class="text-xs text-rose-600">{{ $error }}</p>
    @endif
</div>
