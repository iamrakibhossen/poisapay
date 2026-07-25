@php $hours = max(1, (int) ($props['hours'] ?? 24)); @endphp
<section id="{{ $node->id }}" class="pp-block py-8 text-center"
    x-data="{
        left: {{ $hours * 3600 }},
        pad(n) { return String(n).padStart(2, '0'); },
        get h() { return this.pad(Math.floor(this.left / 3600)); },
        get m() { return this.pad(Math.floor((this.left % 3600) / 60)); },
        get s() { return this.pad(this.left % 60); },
        init() { setInterval(() => { if (this.left > 0) this.left--; }, 1000); },
    }">
    <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $props['label'] ?? __('Offer ends in') }}</p>
    <div class="mt-3 flex justify-center gap-2">
        <span class="min-w-[3rem] rounded-xl px-3 py-2.5 text-xl font-bold text-white shadow-sm" style="background: var(--pp-accent)" x-text="h">00</span>
        <span class="min-w-[3rem] rounded-xl px-3 py-2.5 text-xl font-bold text-white shadow-sm" style="background: var(--pp-accent)" x-text="m">00</span>
        <span class="min-w-[3rem] rounded-xl px-3 py-2.5 text-xl font-bold text-white shadow-sm" style="background: var(--pp-accent)" x-text="s">00</span>
    </div>
</section>
