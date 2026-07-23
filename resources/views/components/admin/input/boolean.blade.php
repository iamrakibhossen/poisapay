@props([
    'name',
    'value' => null,
    'positiveLabel' => __('Enabled'),
    'negativeLabel' => __('Disabled'),
    'disabled' => false,
])
<div class="flex gap-4">
    <label class="inline-flex items-center gap-2 cursor-pointer">
        <input
            type="radio"
            name="{{ $name }}"
            value="1"
            @checked($value)
            @disabled($disabled)
            {{ $attributes->merge(['class' => 'disabled:opacity-50 border border-gray-400 w-4 h-4 form-radio text-brand-500 focus:ring-brand-500']) }}
        /> {{ $positiveLabel }}
    </label>
    <label class="inline-flex items-center gap-2 cursor-pointer">
        <input
            type="radio"
            name="{{ $name }}"
            value="0"
            @checked(! $value)
            @disabled($disabled)
            {{ $attributes->merge(['class' => 'disabled:opacity-50 border border-gray-400 w-4 h-4 form-radio text-brand-500 focus:ring-brand-500']) }}
        /> {{ $negativeLabel }}
    </label>
</div>
