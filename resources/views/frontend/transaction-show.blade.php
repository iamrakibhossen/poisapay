<x-layouts.app :title="__('Transaction')">
    @php $isDebit = ($tx['direction'] ?? '-') === '-'; @endphp
    <div class="mx-auto max-w-2xl space-y-5">
        <nav class="flex items-center gap-1.5 text-sm text-neutral-500" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ route('transactions') }}" class="inline-flex items-center gap-1.5 font-medium transition hover:text-neutral-900">
                <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Transactions') }}
            </a>
            <x-heroicon-o-chevron-right class="h-3.5 w-3.5 text-neutral-300" />
            <span class="text-neutral-400">{{ $tx['type'] }}</span>
        </nav>

        {{-- Header / receipt summary --}}
        <div class="pp-card p-6 text-center">
            <span @class([
                'mx-auto grid h-14 w-14 place-items-center rounded-2xl',
                'bg-neutral-100 text-neutral-500' => $isDebit,
                'bg-emerald-50 text-emerald-600' => ! $isDebit,
            ])>
                <x-dynamic-component :component="'heroicon-o-'.($tx['icon'] ?? 'banknotes')" class="h-6 w-6" />
            </span>
            <p class="mt-3 text-sm font-medium text-neutral-500">{{ $tx['title'] }}</p>
            <p class="mt-1 text-3xl font-bold tracking-tight tabular {{ $isDebit ? 'text-neutral-900' : 'text-emerald-600' }}">{{ $tx['amount'] }}</p>
            <div class="mt-3 flex flex-wrap items-center justify-center gap-2">
                <x-ui.badge :color="$tx['statusColor'] ?? 'gray'" dot>{{ $tx['status'] }}</x-ui.badge>
                <span class="text-xs text-neutral-400">{{ $tx['at_full'] }} · {{ $tx['at_human'] }}</span>
            </div>
        </div>

        {{-- All details --}}
        <div class="pp-card overflow-hidden">
            <div class="border-b border-neutral-100 px-5 py-3">
                <h2 class="text-sm font-semibold text-neutral-900">{{ __('Details') }}</h2>
            </div>
            <dl class="divide-y divide-neutral-100">
                @foreach ($tx['rows'] as $row)
                    <div class="flex items-start justify-between gap-4 px-5 py-3">
                        <dt class="shrink-0 text-sm text-neutral-500">{{ __($row['label']) }}</dt>
                        <dd class="min-w-0 break-words text-right text-sm font-medium text-neutral-900 {{ ($row['mono'] ?? false) ? 'font-mono text-xs' : '' }}">{{ $row['value'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        {{-- Related links --}}
        @if (! empty($tx['related']))
            <div class="flex flex-wrap justify-end gap-2">
                @foreach ($tx['related'] as $rel)
                    <a href="{{ $rel['url'] }}" class="group inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-medium text-neutral-700 transition hover:bg-brand-50/40 hover:text-brand-600">
                        {{ __($rel['label']) }}
                        <x-heroicon-o-chevron-right class="h-4 w-4 text-neutral-400 transition group-hover:translate-x-0.5 group-hover:text-brand-500" />
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-layouts.app>
