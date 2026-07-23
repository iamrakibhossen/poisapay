@props([
    'finish' => 'emerald',      /* emerald | obsidian | titanium | aurora */
    'label' => 'Virtual · Multi-currency',
    'number' => '4291',
    'holder' => 'A. RAHMAN',
    'expiry' => '09 / 29',
    'balance' => '$12,480.55',
    'network' => 'mastercard',  /* mastercard | visa */
    'tilt' => false,            /* enable pointer 3D tilt (hero only) */
    'shine' => true,
])

{{--
    Premium virtual card — reusable across hero + card showcase.
    Finish visuals live in the .poisa-landing scoped stylesheet (home.blade.php).
--}}
<div
    @if ($tilt)
        x-data="ppTilt()" @mousemove="move($event)" @mouseleave="reset()"
        :style="`transform: perspective(1400px) rotateX(${rx}deg) rotateY(${ry}deg)`"
    @endif
    class="pp-card3d card-{{ $finish }} group relative aspect-[1.586/1] w-full select-none overflow-hidden rounded-[1.5rem] p-6 text-white shadow-2xl"
    role="img"
    aria-label="{{ __('PoisaPay virtual :finish card ending :number, balance :balance', ['finish' => $finish, 'number' => $number, 'balance' => $balance]) }}"
>
    {{-- Holographic sheen --}}
    @if ($shine)
        <span aria-hidden="true" class="card-sheen pointer-events-none absolute inset-0"></span>
    @endif
    <span aria-hidden="true" class="pointer-events-none absolute -right-16 -top-20 h-56 w-56 rounded-full opacity-40 blur-2xl" style="background: radial-gradient(circle, rgba(255,255,255,.35), transparent 70%)"></span>

    <div class="relative flex h-full flex-col justify-between">
        {{-- Top: brand + contactless --}}
        <div class="flex items-start justify-between">
            <div>
                <div class="flex items-center gap-1.5">
                    <span class="grid h-6 w-6 place-items-center rounded-lg bg-white/90 text-[#0a0f1c]">
                        <svg viewBox="0 0 24 24" class="h-3.5 w-3.5" fill="currentColor"><path d="M13 2 4.5 12.5H11l-2 9.5 8.5-11H11z"/></svg>
                    </span>
                    <span class="text-[0.95rem] font-bold tracking-tight">PoisaPay</span>
                </div>
                <p class="mt-1 text-[0.62rem] font-medium uppercase tracking-[0.18em] text-white/60">{{ $label }}</p>
            </div>
            {{-- Contactless --}}
            <svg viewBox="0 0 24 24" class="h-6 w-6 text-white/70" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round">
                <path d="M8.5 8a6 6 0 0 1 0 8"/><path d="M11.5 5.5a10 10 0 0 1 0 13"/><path d="M14.5 3a14 14 0 0 1 0 18"/>
            </svg>
        </div>

        {{-- Chip + balance --}}
        <div class="flex items-end justify-between">
            <div>
                <span aria-hidden="true" class="card-chip block h-8 w-11 rounded-md"></span>
                <p class="mt-3 text-[0.62rem] uppercase tracking-[0.16em] text-white/55">{{ __('Balance') }}</p>
                <p class="text-lg font-bold tabular tracking-tight">{{ $balance }}</p>
            </div>
        </div>

        {{-- Number + holder + network --}}
        <div>
            <p class="font-mono text-[0.95rem] tracking-[0.14em] text-white/90">
                •••• &nbsp;•••• &nbsp;•••• &nbsp;{{ $number }}
            </p>
            <div class="mt-3 flex items-end justify-between">
                <div class="leading-tight">
                    <p class="text-[0.55rem] uppercase tracking-[0.16em] text-white/50">{{ __('Card holder') }}</p>
                    <p class="text-[0.8rem] font-semibold tracking-wide">{{ $holder }}</p>
                </div>
                <div class="text-right leading-tight">
                    <p class="text-[0.55rem] uppercase tracking-[0.16em] text-white/50">{{ __('Expires') }}</p>
                    <p class="text-[0.8rem] font-semibold tabular tracking-wide">{{ $expiry }}</p>
                </div>
                @if ($network === 'visa')
                    <span class="ml-3 select-none text-base font-black italic tracking-tight text-white">VISA</span>
                @else
                    <span class="ml-3 flex items-center" aria-hidden="true">
                        <span class="h-6 w-6 rounded-full" style="background:#eb001b"></span>
                        <span class="-ml-2.5 h-6 w-6 rounded-full" style="background:#f79e1b;mix-blend-mode:screen"></span>
                    </span>
                @endif
            </div>
        </div>
    </div>
</div>
