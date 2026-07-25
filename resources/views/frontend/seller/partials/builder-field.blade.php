{{-- One property-panel field, generated from a block's schema field definition.
     In scope: `field` (the definition) and `selected` (the current node getter).
     Every input writes into selected.props[field.key] and calls touched(). --}}
@php
    $inp = 'w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500';
    $lbl = 'mb-1 block text-xs font-medium text-neutral-500';
@endphp

<label class="{{ $lbl }}" x-show="field.type !== 'toggle'" x-text="field.label"></label>

{{-- text / link / image / icon --}}
<template x-if="['text', 'link', 'image', 'icon'].includes(field.type)">
    <input type="text" class="{{ $inp }}" :maxlength="field.max || 500"
        x-model="selected.props[field.key]" @input="touched()" />
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
                            <template x-if="sf.type === 'textarea'">
                                <textarea rows="2" class="{{ $inp }}" :placeholder="sf.label" x-model="row[sf.key]" @input="touched()"></textarea>
                            </template>
                            <template x-if="sf.type !== 'textarea'">
                                <input type="text" class="{{ $inp }}" :placeholder="sf.label" x-model="row[sf.key]" @input="touched()" />
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </template>
        <button type="button" @click="addRow(field)" class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
            <x-heroicon-o-plus class="h-3.5 w-3.5" /> {{ __('Add item') }}
        </button>
    </div>
</template>
