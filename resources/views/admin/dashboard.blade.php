<x-layouts.admin :title="'Admin Dashboard'">
    <div class="space-y-6">
        @php
            $hour = now()->hour;
            $greet = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
            $operator = auth('admin')->user();

            $attention = [
                ['label' => 'KYC to review', 'count' => $stats['pendingKyc'], 'route' => route('admin.kyc'), 'icon' => 'identification', 'color' => 'amber', 'cta' => 'Open queue'],
                ['label' => 'Withdrawals in review', 'count' => $stats['reviewWithdrawals'], 'route' => route('admin.withdrawals'), 'icon' => 'shield-exclamation', 'color' => 'rose', 'cta' => 'Review'],
                ['label' => 'Pending deposits', 'count' => $stats['pendingDeposits'], 'route' => route('admin.deposits'), 'icon' => 'clock', 'color' => 'sky', 'cta' => 'View'],
            ];
            $palette = [
                'amber' => ['ring' => 'ring-amber-200', 'bg' => 'bg-amber-50', 'icon' => 'bg-amber-100 text-amber-600', 'text' => 'text-amber-700'],
                'rose' => ['ring' => 'ring-rose-200', 'bg' => 'bg-rose-50', 'icon' => 'bg-rose-100 text-rose-600', 'text' => 'text-rose-700'],
                'sky' => ['ring' => 'ring-sky-200', 'bg' => 'bg-sky-50', 'icon' => 'bg-sky-100 text-sky-600', 'text' => 'text-sky-700'],
            ];
            $totalAttention = collect($attention)->sum('count');
        @endphp

        {{-- Header --}}
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-neutral-900">{{ $greet }}{{ $operator?->name ? ', '.\Illuminate\Support\Str::before($operator->name, ' ') : '' }}</h1>
                <p class="mt-0.5 text-sm text-neutral-500">Live operational health across custody, compliance and money movement.</p>
            </div>
            <div class="flex items-center gap-2">
                <span @class([
                    'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium',
                    'bg-emerald-50 text-emerald-700' => $totalAttention === 0,
                    'bg-amber-50 text-amber-700' => $totalAttention > 0,
                ])>
                    <span class="h-1.5 w-1.5 rounded-full {{ $totalAttention === 0 ? 'bg-emerald-500' : 'bg-amber-500' }}"></span>
                    {{ $totalAttention === 0 ? 'All clear' : $totalAttention.' item'.($totalAttention === 1 ? '' : 's').' need attention' }}
                </span>
                <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-medium text-neutral-500">{{ now()->format('D, d M Y') }}</span>
            </div>
        </div>

        {{-- Needs attention --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            @foreach ($attention as $item)
                @php $p = $palette[$item['color']]; $active = $item['count'] > 0; @endphp
                <a href="{{ $item['route'] }}" @class([
                    'group flex items-center gap-4 rounded-[var(--radius-card)] border p-5 transition',
                    $p['bg'].' border-transparent ring-1 '.$p['ring'] => $active,
                    'border-neutral-200 bg-white hover:border-neutral-300' => ! $active,
                ])>
                    <span @class([
                        'grid h-12 w-12 shrink-0 place-items-center rounded-xl',
                        $p['icon'] => $active,
                        'bg-neutral-100 text-neutral-400' => ! $active,
                    ])>
                        <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="h-6 w-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $item['label'] }}</p>
                        <p class="tabular mt-0.5 text-2xl font-bold tracking-tight {{ $active ? $p['text'] : 'text-neutral-400' }}">{{ number_format($item['count']) }}</p>
                    </div>
                    <span class="flex items-center gap-1 text-xs font-semibold {{ $active ? $p['text'] : 'text-neutral-400' }}">
                        <span class="hidden sm:inline">{{ $active ? $item['cta'] : 'Clear' }}</span>
                        <x-heroicon-o-arrow-right class="h-4 w-4 transition group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach
        </div>

        {{-- Overview KPIs --}}
        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.stat-card label="Total Users" :value="number_format($stats['users'])" icon="users" accent="emerald" />
            <x-ui.stat-card label="New Users (7d)" :value="number_format($stats['newUsers7d'])" icon="user-plus" accent="brand" />
            <x-ui.stat-card label="Deposits Today" :value="number_format($stats['depositsToday'])" icon="arrow-down-tray" accent="emerald" />
            <x-ui.stat-card label="Transfers (24h)" :value="number_format($stats['transfers24h'])" icon="arrows-right-left" accent="brand" />
        </div>

        {{-- Chart + KYC queue --}}
        <div class="grid grid-cols-1 gap-5 lg:grid-cols-2">
            <div class="pp-card p-5">
                <div class="flex items-start justify-between">
                    <h2 class="flex items-center text-md font-semibold text-neutral-900">
                        <x-heroicon-o-arrow-trending-up class="mr-2 inline-block h-6 w-6 text-emerald-500" />
                        <span>Deposit activity <span class="font-normal text-neutral-400">· last 14 days</span></span>
                    </h2>
                    <div class="text-right">
                        <p class="tabular text-lg font-bold text-neutral-900">{{ number_format($chartValues->sum()) }}</p>
                        <p class="text-[11px] text-neutral-400">{{ number_format($stats['journalLines']) }} ledger lines</p>
                    </div>
                </div>
                <div x-data="{
                        init() {
                            window.ppChart(this.$refs.canvas, {
                                type: 'line',
                                data: {
                                    labels: @js($chartLabels),
                                    datasets: [{
                                        label: 'Deposits',
                                        data: @js($chartValues),
                                        borderColor: '#10b981',
                                        backgroundColor: 'rgba(16,185,129,0.12)',
                                        fill: true, tension: 0.3, borderWidth: 2,
                                        pointRadius: 0, pointHoverRadius: 4,
                                    }]
                                },
                                options: {
                                    responsive: true, maintainAspectRatio: false,
                                    plugins: { legend: { display: false } },
                                    scales: {
                                        x: { grid: { display: false }, ticks: { color: '#9ca3af', maxRotation: 0, autoSkip: true } },
                                        y: { beginAtZero: true, grid: { color: 'rgba(148,163,184,0.15)' }, ticks: { color: '#9ca3af', precision: 0 } }
                                    }
                                }
                            });
                        }
                    }" class="mt-4 h-[280px]">
                    <canvas x-ref="canvas"></canvas>
                </div>
            </div>

            <div class="pp-card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="flex items-center text-md font-semibold text-neutral-900">
                        <x-heroicon-o-identification class="mr-2 inline-block h-6 w-6 text-amber-500" />
                        <span>KYC approval queue</span>
                    </h2>
                    <a href="{{ route('admin.kyc') }}" class="text-xs font-semibold text-amber-700 hover:text-amber-800">Open queue →</a>
                </div>
                @forelse ($kycQueue as $profile)
                    <a href="{{ route('admin.kyc') }}" class="-mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5 transition hover:bg-neutral-50 {{ ! $loop->last ? 'border-b border-neutral-100' : '' }}">
                        <x-ui.avatar :name="$profile->user->name" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-neutral-900">{{ $profile->user->name }}</p>
                            <p class="text-xs text-neutral-500">Requested {{ $profile->requested_tier->label() }} · {{ $profile->created_at->diffForHumans() }}</p>
                        </div>
                        <x-ui.badge :color="$profile->status->color()">{{ $profile->status->label() }}</x-ui.badge>
                    </a>
                @empty
                    <x-ui.empty-state icon="check-badge" title="Queue clear" description="No pending verifications." />
                @endforelse
            </div>
        </div>

        {{-- Withdrawal review --}}
        <div class="pp-card p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="flex items-center text-md font-semibold text-neutral-900">
                    <x-heroicon-o-shield-exclamation class="mr-2 inline-block h-6 w-6 text-rose-500" />
                    <span>Withdrawal review</span>
                </h2>
                <a href="{{ route('admin.withdrawals') }}" class="text-xs font-semibold text-amber-700 hover:text-amber-800">Review all →</a>
            </div>
            <x-ui.table :headers="['User', 'Amount', 'Risk', 'Status', 'Requested']">
                @forelse ($reviewQueue as $w)
                    <tr class="cursor-pointer border-b border-neutral-100 transition hover:bg-neutral-50" onclick="window.location='{{ route('admin.withdrawals') }}'">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <x-ui.avatar :name="$w->user->name" size="sm" />
                                <span class="text-sm font-medium text-neutral-900">{{ $w->user->name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <x-ui.asset-icon :symbol="$w->asset->symbol" size="sm" />
                                <span class="tabular text-sm font-semibold text-neutral-900">{{ $w->money()->format() }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3"><x-ui.badge :color="$w->risk_level->color()">{{ $w->risk_level->label() }}</x-ui.badge></td>
                        <td class="px-4 py-3"><x-ui.badge :color="$w->status->color()" dot>{{ $w->status->label() }}</x-ui.badge></td>
                        <td class="px-4 py-3 text-sm text-neutral-500">{{ $w->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state icon="shield-check" title="Nothing to review" description="No flagged withdrawals." /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-layouts.admin>
