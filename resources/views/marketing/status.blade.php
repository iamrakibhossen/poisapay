<x-layouts.marketing :title="__('System Status')" :description="__('Live operational status of PoisaPay services.')">
    @php
        // Per-status presentation (badge chip + dot colour).
        $meta = [
            'operational' => ['label' => __('Operational'), 'dot' => 'bg-emerald-500', 'chip' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
            'degraded'    => ['label' => __('Degraded'),    'dot' => 'bg-amber-500',   'chip' => 'bg-amber-50 text-amber-700 ring-amber-200'],
            'maintenance' => ['label' => __('Maintenance'), 'dot' => 'bg-sky-500',     'chip' => 'bg-sky-50 text-sky-700 ring-sky-200'],
            'down'        => ['label' => __('Outage'),      'dot' => 'bg-rose-500',    'chip' => 'bg-rose-50 text-rose-700 ring-rose-200'],
        ];

        // Overall banner headline + accent per aggregate status.
        $banner = [
            'operational' => ['title' => __('All systems operational'),   'icon' => 'check-circle',        'grad' => 'from-emerald-500 to-emerald-600', 'ring' => 'ring-emerald-200'],
            'degraded'    => ['title' => __('Degraded performance'),       'icon' => 'exclamation-triangle', 'grad' => 'from-amber-500 to-amber-600',      'ring' => 'ring-amber-200'],
            'maintenance' => ['title' => __('Some services under maintenance'), 'icon' => 'wrench-screwdriver', 'grad' => 'from-sky-500 to-sky-600',      'ring' => 'ring-sky-200'],
            'down'        => ['title' => __('Partial service outage'),     'icon' => 'x-circle',            'grad' => 'from-rose-500 to-rose-600',        'ring' => 'ring-rose-200'],
        ];
        $b = $banner[$overall];
    @endphp

    <div class="mx-auto max-w-3xl px-4 pb-24 pt-14 sm:px-6 sm:pt-20">
        <div class="mb-6">
            <h1 class="text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ __('System Status') }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ __('Live health of PoisaPay services. This page reflects checks run when it loaded.') }}</p>
        </div>

        {{-- Overall banner --}}
        <div class="glass-card flex items-center gap-4 p-6 ring-1 {{ $b['ring'] }}">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br {{ $b['grad'] }} text-white shadow-sm">
                <x-dynamic-component :component="'heroicon-o-'.$b['icon']" class="h-6 w-6" />
            </span>
            <div class="min-w-0">
                <p class="text-lg font-bold text-slate-900">{{ $b['title'] }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ __('Last checked') }} {{ $checkedAt->format('j M Y, H:i') }} ({{ $checkedAt->timezone(config('app.timezone'))->format('T') }})</p>
            </div>
        </div>

        {{-- Component list --}}
        <div class="glass-card mt-6 divide-y divide-slate-100 p-2 sm:p-3">
            @foreach ($components as $c)
                @php $m = $meta[$c['status']]; @endphp
                <div class="flex items-center justify-between gap-4 px-3 py-4">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">{{ $c['name'] }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $c['description'] }}</p>
                    </div>
                    <span class="inline-flex shrink-0 items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 {{ $m['chip'] }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ $m['dot'] }}"></span>{{ $m['label'] }}
                    </span>
                </div>
            @endforeach
        </div>

        {{-- Legend --}}
        <div class="mt-6 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs text-slate-500">
            @foreach ($meta as $m)
                <span class="inline-flex items-center gap-1.5"><span class="h-1.5 w-1.5 rounded-full {{ $m['dot'] }}"></span>{{ $m['label'] }}</span>
            @endforeach
        </div>

        <p class="mt-8 text-center text-xs text-slate-400">
            {{ __('Reload this page for the latest status.') }}
            <a href="{{ route('faqs.public') }}" class="font-medium text-brand-700 hover:underline">{{ __('Need help?') }}</a>
        </p>
    </div>
</x-layouts.marketing>
