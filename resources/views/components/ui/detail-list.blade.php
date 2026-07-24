@props(['heading' => null])

{{-- A bordered, hairline-divided definition list for detail modals. Optional
     small-caps heading rendered above the list. Fill with <x-ui.detail-row>. --}}
<div {{ $attributes->merge(['class' => 'space-y-3']) }}>
    @if ($heading)
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $heading }}</p>
    @endif
    <dl class="divide-y divide-slate-100 rounded-xl border border-slate-100">
        {{ $slot }}
    </dl>
</div>
