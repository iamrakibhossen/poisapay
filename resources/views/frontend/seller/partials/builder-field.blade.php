{{-- One property-panel field, generated from a block's schema field definition.
     In scope: `field` (the definition) and `selected` (the current node getter).
     Every input writes into selected.props[field.key] and calls touched().
     Image fields open the Media Library picker instead of a manual URL input. --}}
@php
    $inp = 'w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500';
    $lbl = 'mb-1 block text-xs font-medium text-neutral-500';
    $pickBtn = 'flex w-full items-center justify-center gap-2 rounded-lg border border-dashed border-neutral-300 py-3 text-xs font-medium text-neutral-500 transition hover:border-brand-400 hover:text-brand-600';
@endphp

<label class="{{ $lbl }}" x-show="field.type !== 'toggle'" x-text="field.label"></label>

{{-- variant → a segmented "Layout" picker (each choice is a distinct section layout) --}}
<template x-if="field.type === 'variant'">
    <div class="grid grid-cols-2 gap-1.5">
        <template x-for="[val, label] in Object.entries(field.options || {})" :key="val">
            <button type="button" @click="selected.props[field.key] = val; touched()"
                :class="(selected.props[field.key] || '') === val ? 'border-brand-500 bg-brand-50 text-brand-700 ring-1 ring-brand-500/20' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50'"
                class="rounded-lg border px-2 py-2 text-xs font-semibold transition" x-text="label"></button>
        </template>
    </div>
</template>

{{-- text / link / icon (image is handled separately via the Media Library) --}}
<template x-if="['text', 'link', 'icon'].includes(field.type)">
    <input type="text" class="{{ $inp }}" :maxlength="field.max || 500"
        x-model="selected.props[field.key]" @input="touched()" />
</template>

{{-- image → Media Library picker --}}
<template x-if="field.type === 'image'">
    <div>
        <template x-if="selected.props[field.key]">
            <div class="flex items-center gap-2">
                <img :src="selected.props[field.key]" alt="" class="h-12 w-16 shrink-0 rounded-lg border border-neutral-200 object-cover" />
                <button type="button" @click="pickImage(field.key)" class="flex-1 rounded-lg border border-neutral-200 px-2 py-2 text-xs font-medium text-neutral-600 transition hover:bg-neutral-50">{{ __('Change image') }}</button>
                <button type="button" @click="clearImage(field.key)" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-neutral-300 hover:bg-rose-50 hover:text-rose-500" title="{{ __('Remove') }}"><x-heroicon-o-x-mark class="h-4 w-4" /></button>
            </div>
        </template>
        <template x-if="!selected.props[field.key]">
            <button type="button" @click="pickImage(field.key)" class="{{ $pickBtn }}">
                <x-heroicon-o-photo class="h-4 w-4" /> {{ __('Choose image') }}
            </button>
        </template>
    </div>
</template>

{{-- textarea / richtext --}}
<template x-if="['textarea', 'richtext'].includes(field.type)">
    <textarea rows="3" class="{{ $inp }}" x-model="selected.props[field.key]" @input="touched()"></textarea>
</template>

{{-- number --}}
<template x-if="field.type === 'number'">
    <input type="number" class="{{ $inp }}" :min="field.min" :max="field.max"
        x-model.number="selected.props[field.key]" @input="touched()" />
</template>

{{-- toggle --}}
<template x-if="field.type === 'toggle'">
    <label class="flex items-center gap-2">
        <input type="checkbox" x-model="selected.props[field.key]" @change="touched()"
            class="h-4 w-4 rounded border-neutral-300 text-brand-500 focus:ring-brand-500" />
        <span class="text-sm text-neutral-700" x-text="field.label"></span>
    </label>
</template>

{{-- select --}}
<template x-if="field.type === 'select'">
    <select class="{{ $inp }}" x-model="selected.props[field.key]" @change="touched()">
        <template x-for="[val, label] in Object.entries(field.options || {})" :key="val">
            <option :value="val" x-text="label"></option>
        </template>
    </select>
</template>

{{-- color --}}
<template x-if="field.type === 'color'">
    <div class="flex items-center gap-2">
        <input type="color" x-model="selected.props[field.key]" @input="touched()" class="h-9 w-12 cursor-pointer rounded-lg border border-neutral-200" />
        <input type="text" class="{{ $inp }}" x-model="selected.props[field.key]" @input="touched()" placeholder="#000000" />
    </div>
</template>

{{-- repeater --}}
<template x-if="field.type === 'repeater'">
    <div class="space-y-2">
        <template x-for="(row, i) in repeaterRows(field.key)" :key="i">
            <div class="rounded-lg border border-neutral-200 p-2.5">
                <div class="mb-1.5 flex items-center justify-between">
                    <span class="text-[11px] font-medium text-neutral-400" x-text="'#' + (i + 1)"></span>
                    <button type="button" @click="removeRow(field.key, i)" class="text-neutral-300 hover:text-rose-500"><x-heroicon-o-x-mark class="h-3.5 w-3.5" /></button>
                </div>
                <div class="space-y-2">
                    <template x-for="sf in field.item" :key="sf.key">
                        <div>
                            {{-- image sub-field → picker --}}
                            <template x-if="sf.type === 'image'">
                                <div>
                                    <label class="mb-1 block text-[11px] font-medium text-neutral-400" x-text="sf.label"></label>
                                    <div class="flex items-center gap-2">
                                        <template x-if="row[sf.key]"><img :src="row[sf.key]" alt="" class="h-9 w-12 shrink-0 rounded border border-neutral-200 object-cover" /></template>
                                        <button type="button" @click="pickRepeaterImage(field.key, i, sf.key)" class="flex-1 rounded-md border border-neutral-200 px-2 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50" x-text="row[sf.key] ? '{{ __('Change') }}' : '{{ __('Choose image') }}'"></button>
                                        <template x-if="row[sf.key]"><button type="button" @click="row[sf.key] = ''; touched()" class="grid h-7 w-7 shrink-0 place-items-center rounded text-neutral-300 hover:text-rose-500"><x-heroicon-o-x-mark class="h-3.5 w-3.5" /></button></template>
                                    </div>
                                </div>
                            </template>
                            <template x-if="sf.type === 'textarea'">
                                <textarea rows="2" class="{{ $inp }}" :placeholder="sf.label" x-model="row[sf.key]" @input="touched()"></textarea>
                            </template>
                            <template x-if="sf.type === 'select'">
                                <select class="{{ $inp }}" x-model="row[sf.key]" @change="touched()">
                                    <template x-for="[val, label] in Object.entries(sf.options || {})" :key="val">
                                        <option :value="val" x-text="label"></option>
                                    </template>
                                </select>
                            </template>
                            <template x-if="sf.type !== 'textarea' && sf.type !== 'image' && sf.type !== 'select'">
                                <input type="text" class="{{ $inp }}" :placeholder="sf.label" x-model="row[sf.key]" @input="touched()" />
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        <div class="flex flex-wrap gap-2">
            <button type="button" @click="addRow(field)" class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                <x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add item') }}
            </button>
            {{-- bulk image picker for image repeaters (galleries, sliders, logos…) --}}
            <template x-if="(field.item || []).some((f) => f.type === 'image')">
                <button type="button" @click="pickImagesInto(field)" class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                    <x-heroicon-o-photo class="h-3.5 w-3.5" /> {{ __('Choose images') }}
                </button>
            </template>
        </div>
    </div>
</template>
