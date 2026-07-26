@php $rows = $props['items'] ?? []; @endphp
@if (! empty($rows))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
        <div class="mx-auto max-w-3xl px-5">
            @if (! empty($props['heading']))<h2 class="text-center text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] }}</h2>@endif
            <div class="mt-8 overflow-hidden rounded-2xl border border-neutral-200">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-neutral-50 text-left">
                            <th class="px-4 py-3 font-semibold text-neutral-500"></th>
                            <th class="px-4 py-3 text-center font-bold" style="color: var(--pp-accent)">{{ $props['usLabel'] ?? __('Us') }}</th>
                            <th class="px-4 py-3 text-center font-semibold text-neutral-400">{{ $props['themLabel'] ?? __('Others') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @foreach ($rows as $r)
                            @php $r = is_array($r) ? $r : ['label' => $r]; @endphp
                            <tr>
                                <td class="px-4 py-3 font-medium text-neutral-700">{{ $r['label'] ?? '' }}</td>
                                <td class="px-4 py-3 text-center">@if (! empty($r['us']))<x-heroicon-s-check class="mx-auto h-5 w-5" style="color: var(--pp-accent)" />@else<span class="text-neutral-300">—</span>@endif</td>
                                <td class="px-4 py-3 text-center">@if (! empty($r['them']))<x-heroicon-s-check class="mx-auto h-5 w-5 text-neutral-300" />@else<x-heroicon-o-x-mark class="mx-auto h-5 w-5 text-neutral-300" />@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endif
