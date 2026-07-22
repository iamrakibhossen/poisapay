@props(['symbol' => '', 'size' => 'md'])

@php
    $sizes = ['sm' => 'h-7 w-7 text-[10px]', 'md' => 'h-9 w-9 text-xs', 'lg' => 'h-11 w-11 text-sm'];
    $palette = [
        'USDT' => 'bg-emerald-500', 'USDC' => 'bg-sky-500', 'ETH' => 'bg-indigo-500',
        'BNB' => 'bg-amber-500', 'TRX' => 'bg-rose-500', 'BTC' => 'bg-orange-500',
        'BDT' => 'bg-green-600', 'USD' => 'bg-neutral-600', 'EUR' => 'bg-blue-600',
    ];
    $bg = $palette[$symbol] ?? 'bg-brand-500';
@endphp

<span {{ $attributes->merge(['class' => 'inline-grid shrink-0 place-items-center rounded-full font-bold text-white '.($sizes[$size] ?? $sizes['md']).' '.$bg]) }}>
    {{ \Illuminate\Support\Str::substr($symbol, 0, 4) }}
</span>
