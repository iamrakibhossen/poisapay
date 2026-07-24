@props(['label' => '', 'value' => null])

{{-- One row of an <x-ui.detail-list>. Pass a simple :value, or use the slot for
     rich content (copy buttons, links). Attributes merge onto the <dd> so callers
     can add `class="tabular"`, alignment, etc. --}}
<div class="flex items-center justify-between gap-4 px-4 py-3">
    <dt class="shrink-0 text-sm text-slate-500">{{ $label }}</dt>
    <dd {{ $attributes->merge(['class' => 'min-w-0 text-sm font-medium text-slate-900']) }}>{{ $value ?? $slot }}</dd>
</div>
