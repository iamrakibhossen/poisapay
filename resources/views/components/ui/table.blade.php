@props([
    'headers' => [],
    'sticky' => false,   // sticky header on vertical scroll
])

{{-- Clean data table: rounded card container, soft shadow, hairline header.
     Rows provide their own cells; use e.g. class="hover:bg-gray-50" on <tr>. --}}
<div {{ $attributes->merge(['class' => 'overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-[var(--shadow-card)]']) }}>
    <table class="min-w-full text-sm">
        @if (count($headers))
            <thead>
                <tr @class([
                    'border-b border-gray-200 bg-gray-50/70 text-[11px] uppercase tracking-wider text-gray-500',
                    'sticky top-0 z-10' => $sticky,
                ])>
                    @foreach ($headers as $header)
                        <th scope="col" class="whitespace-nowrap px-4 py-3 text-left font-semibold">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
        @endif
        <tbody class="text-gray-600">
            {{ $slot }}
        </tbody>
    </table>
    @isset($footer)
        <div class="border-t border-gray-200 bg-gray-50/70 px-4 py-3">{{ $footer }}</div>
    @endisset
</div>
