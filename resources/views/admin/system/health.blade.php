<x-layouts.admin :title="__('Server Health')">
    @php
        // Full static classes (never interpolate colour into a class — Tailwind purges those).
        $meta = [
            'ok' => ['bg-emerald-50 text-emerald-600', 'check-circle', __('Operational'), 'success'],
            'warn' => ['bg-amber-50 text-amber-600', 'exclamation-triangle', __('Degraded'), 'warning'],
            'down' => ['bg-red-50 text-red-600', 'x-circle', __('Down'), 'danger'],
        ];
        $overall = collect($checks)->contains(fn ($c) => $c['status'] === 'down') ? 'down'
            : (collect($checks)->contains(fn ($c) => $c['status'] === 'warn') ? 'warn' : 'ok');
    @endphp

    <div class="space-y-6" x-data="{ }" x-init="setTimeout(() => window.location.reload(), 30000)">
        <x-ui.page-header :title="__('Server Health')" :subtitle="__('Live status of the platform\'s core dependencies. Auto-refreshes every 30s.')">
            <x-slot:actions>
                <x-ui.badge :color="$meta[$overall][3]" dot>{{ $meta[$overall][2] }}</x-ui.badge>
                <x-ui.button :href="route('admin.system-health')" variant="secondary" size="sm" icon="arrow-path">{{ __('Refresh') }}</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Dependency checks --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($checks as $c)
                @php [$tint, $icon] = $meta[$c['status']]; @endphp
                <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-[var(--shadow-card)]">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-900">{{ $c['label'] }}</p>
                        <span class="grid h-8 w-8 place-items-center rounded-full {{ $tint }}">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">{{ $c['detail'] }}</p>
                    <p class="mt-1 text-xs text-gray-400 tabular">{{ $c['ms'] }} ms</p>
                </div>
            @endforeach
        </div>

        {{-- App info --}}
        <x-ui.card :title="__('Application')">
            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
                @foreach ($app as $label => $value)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ $label }}</dt>
                        <dd class="mt-1 font-mono text-sm text-gray-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    </div>
</x-layouts.admin>
