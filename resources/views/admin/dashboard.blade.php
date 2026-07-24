<x-layouts.admin :title="__('Dashboard')">
    @php
        $hour = now()->hour;
        $greet = $hour < 12 ? __('Good morning') : ($hour < 17 ? __('Good afternoon') : __('Good evening'));
        $operator = auth('admin')->user();
        $usd = fn ($v) => '$'.number_format((float) $v, 2);

        $needsAttention = collect($attention)->where('active', true);
        $attentionTotal = $needsAttention->sum('count');

        $sev = [
            'critical' => ['tile' => 'bg-rose-100 text-rose-600', 'bar' => 'bg-rose-500', 'text' => 'text-rose-700'],
            'warn' => ['tile' => 'bg-amber-100 text-amber-600', 'bar' => 'bg-amber-500', 'text' => 'text-amber-700'],
            'info' => ['tile' => 'bg-sky-100 text-sky-600', 'bar' => 'bg-sky-500', 'text' => 'text-sky-700'],
        ];
        $healthColor = ['ok' => 'bg-emerald-500', 'warn' => 'bg-amber-500', 'down' => 'bg-rose-500'];
    @endphp

    <div class="space-y-6">
        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900">{{ $greet }}{{ $operator?->name ? ', '.\Illuminate\Support\Str::before($operator->name, ' ') : '' }}</h1>
                <p class="mt-0.5 text-sm text-neutral-500">{{ __('Operational health across custody, compliance and money movement.') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span @class([
                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium',
                    'bg-emerald-50 text-emerald-700' => $attentionTotal === 0,
                    'bg-amber-50 text-amber-700' => $attentionTotal > 0,
                ])>
                    <span class="h-1.5 w-1.5 rounded-full {{ $attentionTotal === 0 ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                    {{ $attentionTotal === 0 ? __('All clear') : $attentionTotal.' '.__('items need attention') }}
                </span>
                <a href="{{ route('admin.analytics') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-[#002044] px-3 py-2 text-xs font-semibold text-white transition hover:bg-[#00306a]">
                    <x-heroicon-o-chart-bar-square class="h-4 w-4" /> {{ __('Open analytics') }}
                </a>
            </div>
        </div>

        {{-- Financial pulse --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($pulse as $kpi)
                <x-analytics.kpi :kpi="$kpi" :compare="true" />
            @endforeach
        </div>

        {{-- Needs attention --}}
        <div>
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-neutral-900">{{ __('Needs attention') }}</h2>
                @if ($attentionTotal > 0)
                    <span class="text-xs text-neutral-400">{{ $needsAttention->count() }} {{ __('active queues') }}</span>
                @endif
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($attention as $item)
                    @php $p = $sev[$item['severity']]; @endphp
                    <a href="{{ $item['route'] }}"
                       class="pp-card group relative flex items-center gap-3 overflow-hidden p-4 transition duration-200 hover:-translate-y-0.5 hover:shadow-[var(--shadow-pop)]">
                        <span class="absolute inset-x-0 top-0 h-0.5 {{ $item['active'] ? $p['bar'] : 'bg-neutral-200' }}"></span>
                        <span @class([
                            'grid h-10 w-10 shrink-0 place-items-center rounded-lg',
                            $p['tile'] => $item['active'],
                            'bg-neutral-100 text-neutral-400' => ! $item['active'],
                        ])>
                            <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0">
                            <p class="tabular text-xl font-bold text-neutral-900">{{ number_format($item['count']) }}</p>
                            <p class="truncate text-[11px] font-medium text-neutral-500">{{ __($item['label']) }}</p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Money movement + treasury --}}
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <x-analytics.chart :chart="$flowChart" />
            </div>

            <div class="pp-card flex flex-col p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-neutral-900">{{ __('Treasury & solvency') }}</h3>
                    <span @class([
                        'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold',
                        'bg-emerald-50 text-emerald-700' => $treasury['solvent'],
                        'bg-rose-50 text-rose-700' => ! $treasury['solvent'],
                    ])>
                        <span class="h-1.5 w-1.5 rounded-full {{ $treasury['solvent'] ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                        {{ $treasury['solvent'] ? __('Solvent') : __('Deficit') }}
                    </span>
                </div>

                <div class="flex items-baseline gap-2">
                    <span class="font-display tabular text-3xl text-neutral-900">{{ $treasury['ratio'] }}%</span>
                    <span class="text-xs text-neutral-400">{{ __('reserve ratio') }}</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-neutral-100">
                    <div class="h-full rounded-full {{ $treasury['solvent'] ? 'bg-emerald-500' : 'bg-rose-500' }}"
                         style="width: {{ min(100, max(2, $treasury['ratio'])) }}%"></div>
                </div>
                <p class="mt-1.5 text-[11px] text-neutral-400">{{ $usd($treasury['reserve']) }} {{ __('reserve vs') }} {{ $usd($treasury['liabilities']) }} {{ __('liabilities') }}</p>

                <div class="mt-4 space-y-2 border-t border-neutral-100 pt-4">
                    @foreach ([['Hot wallet', $treasury['hot'], 'bg-rose-400'], ['Cold storage', $treasury['cold'], 'bg-sky-400'], ['Pending sweep', $treasury['pending'], 'bg-amber-400']] as [$label, $val, $dot])
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 text-neutral-500"><span class="h-2 w-2 rounded-full {{ $dot }}"></span>{{ __($label) }}</span>
                            <span class="tabular font-semibold text-neutral-800">{{ $usd($val) }}</span>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('admin.treasury') }}" class="mt-4 inline-flex items-center gap-1 text-xs font-semibold text-brand-700 hover:text-brand-800">
                    {{ __('Treasury detail') }} <x-heroicon-o-arrow-right class="h-3.5 w-3.5" />
                </a>
            </div>
        </div>

        {{-- Operational queues --}}
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            {{-- KYC queue --}}
            <div class="pp-card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="flex items-center text-md font-semibold text-neutral-900">
                        <x-heroicon-o-identification class="mr-2 inline-block h-6 w-6 text-amber-500" />
                        <span>{{ __('KYC approval queue') }}</span>
                    </h2>
                    <a href="{{ route('admin.kyc') }}" class="text-xs font-semibold text-amber-700 hover:text-amber-800">{{ __('Open queue') }} →</a>
                </div>
                @forelse ($kycQueue as $profile)
                    <a href="{{ route('admin.kyc') }}" class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5 transition hover:bg-neutral-50 {{ ! $loop->last ? 'border-b border-neutral-100' : '' }}">
                        <x-ui.avatar :name="$profile->user->name" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-neutral-900">{{ $profile->user->name }}</p>
                            <p class="text-xs text-neutral-500">{{ __('Requested') }} {{ $profile->requested_tier->label() }} · {{ $profile->created_at->diffForHumans() }}</p>
                        </div>
                        <x-ui.badge :color="$profile->status->color()">{{ $profile->status->label() }}</x-ui.badge>
                    </a>
                @empty
                    <x-ui.empty-state icon="check-badge" :title="__('Queue clear')" :description="__('No pending verifications.')" />
                @endforelse
            </div>

            {{-- Withdrawal review --}}
            <div class="pp-card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="flex items-center text-md font-semibold text-neutral-900">
                        <x-heroicon-o-shield-exclamation class="mr-2 inline-block h-6 w-6 text-rose-500" />
                        <span>{{ __('Withdrawal review') }}</span>
                    </h2>
                    <a href="{{ route('admin.withdrawals') }}" class="text-xs font-semibold text-rose-700 hover:text-rose-800">{{ __('Review all') }} →</a>
                </div>
                @forelse ($reviewQueue as $w)
                    <a href="{{ route('admin.withdrawals') }}" class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5 transition hover:bg-neutral-50 {{ ! $loop->last ? 'border-b border-neutral-100' : '' }}">
                        <x-ui.avatar :name="$w->user->name" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-neutral-900">{{ $w->user->name }}</p>
                            <p class="text-xs text-neutral-500">{{ $w->risk_level?->label() }} {{ __('risk') }} · {{ $w->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <x-ui.asset-icon :symbol="$w->asset->symbol" size="sm" />
                            <span class="tabular text-sm font-semibold text-neutral-900">{{ $w->money()->format() }}</span>
                        </div>
                    </a>
                @empty
                    <x-ui.empty-state icon="shield-check" :title="__('Nothing to review')" :description="__('No flagged withdrawals.')" />
                @endforelse
            </div>
        </div>

        {{-- System health --}}
        <div class="pp-card p-4">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
                <span class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-400">
                    <x-heroicon-o-heart class="h-4 w-4" /> {{ __('System health') }}
                </span>
                @foreach ($health as $h)
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full {{ $healthColor[$h['status']] ?? 'bg-neutral-300' }}"></span>
                        <span class="text-sm font-medium text-neutral-700">{{ __($h['label']) }}</span>
                        <span class="text-xs text-neutral-400">{{ $h['detail'] }}</span>
                    </div>
                @endforeach
                <a href="{{ route('admin.system-health') }}" class="ml-auto text-xs font-semibold text-brand-700 hover:text-brand-800">{{ __('Details') }} →</a>
            </div>
        </div>
    </div>
</x-layouts.admin>
