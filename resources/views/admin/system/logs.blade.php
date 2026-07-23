<x-layouts.admin :title="__('Logs')">
    @php
        $levelTint = [
            'emergency' => 'bg-red-100 text-red-800', 'alert' => 'bg-red-100 text-red-800',
            'critical' => 'bg-red-100 text-red-800', 'error' => 'bg-red-50 text-red-700',
            'warning' => 'bg-amber-50 text-amber-700', 'notice' => 'bg-sky-50 text-sky-700',
            'info' => 'bg-gray-100 text-gray-600', 'debug' => 'bg-gray-100 text-gray-500',
        ];
    @endphp

    <div class="space-y-6">
        <x-ui.page-header :title="__('Application Logs')" :subtitle="__('Newest entries from the Laravel log (:kb KB).', ['kb' => number_format($sizeKb)])">
            <x-slot:actions>
                @if ($exists)
                    <x-ui.button :href="route('admin.logs.download')" variant="secondary" size="sm" icon="arrow-down-tray">{{ __('Download') }}</x-ui.button>
                    <form method="POST" action="{{ route('admin.logs.clear') }}">
                        @csrf
                        <x-ui.button type="submit" variant="ghost" size="sm" icon="trash">{{ __('Clear') }}</x-ui.button>
                    </form>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif

        {{-- Level filter --}}
        <div class="-mx-1 flex flex-nowrap gap-1.5 overflow-x-auto px-1 pb-1">
            @foreach ($levels as $l)
                <a href="{{ route('admin.logs', ['level' => $l]) }}"
                    @class([
                        'shrink-0 rounded-full px-3.5 py-1.5 text-sm font-medium transition',
                        'bg-gray-900 text-white' => $level === $l,
                        'text-gray-500 hover:bg-gray-100 hover:text-gray-800' => $level !== $l,
                    ])>{{ ucfirst($l) }}</a>
            @endforeach
        </div>

        @if (count($entries))
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[var(--shadow-card)]">
                <div class="divide-y divide-gray-100">
                    @foreach ($entries as $e)
                        <div class="flex items-start gap-3 px-4 py-3">
                            <span class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $levelTint[$e['level']] ?? 'bg-gray-100 text-gray-600' }}">{{ $e['level'] }}</span>
                            <div class="min-w-0 flex-1">
                                <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-relaxed text-gray-800">{{ $e['message'] }}</pre>
                                <p class="mt-1 text-[11px] text-gray-400 tabular">{{ $e['time'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <x-ui.card>
                <x-ui.empty-state icon="document-magnifying-glass" :title="__('No log entries')"
                    :description="$exists ? __('No entries match this level.') : __('The log file does not exist yet.')" />
            </x-ui.card>
        @endif
    </div>
</x-layouts.admin>
