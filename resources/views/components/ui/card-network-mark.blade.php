@props(['network' => 'visa'])

{{-- Payment-network brand mark for card art. Mastercard = interlocking circles,
     Visa = wordmark. --}}
@if ($network === 'mastercard')
    <span class="relative inline-flex items-center" aria-label="Mastercard" role="img">
        <span class="h-6 w-6 rounded-full bg-[#EB001B]"></span>
        <span class="-ml-2.5 h-6 w-6 rounded-full bg-[#FF9E1B]/90"></span>
    </span>
@else
    <span class="text-xl font-bold italic leading-none tracking-tight text-white" aria-label="Visa" role="img">VISA</span>
@endif
