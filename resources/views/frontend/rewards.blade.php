<x-layouts.app :title="'Rewards & Referrals'">
    @php
        $assetBg = fn (string $symbol) => [
            'USDT' => 'bg-emerald-500', 'USDC' => 'bg-sky-500', 'ETH' => 'bg-indigo-500',
            'BNB' => 'bg-amber-500', 'TRX' => 'bg-rose-500', 'BTC' => 'bg-orange-500',
            'BDT' => 'bg-green-600', 'USD' => 'bg-neutral-600', 'EUR' => 'bg-blue-600',
        ][$symbol] ?? 'bg-brand-500';

        $initials = function (?string $name) {
            $parts = array_filter(preg_split('/\s+/', trim((string) $name)));
            $letters = array_map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)), array_slice($parts, 0, 2));
            return implode('', $letters) ?: '?';
        };

        $avatarHue = function (?string $name) {
            $h = 0;
            $s = (string) $name;
            for ($i = 0; $i < strlen($s); $i++) {
                $h = ($h * 31 + ord($s[$i])) & 0xFFFFFFFF;
            }
            return $h % 360;
        };

        $badgeClass = fn (?string $color) => [
            'gray' => 'bg-gray-100 text-gray-600 border-gray-200',
            'success' => 'bg-green-100 text-green-700 border-green-200',
            'warning' => 'bg-amber-100 text-amber-700 border-amber-200',
            'danger' => 'bg-red-100 text-red-700 border-red-200',
            'info' => 'bg-blue-100 text-blue-700 border-blue-200',
            'primary' => 'bg-amber-100 text-amber-700 border-amber-200',
            'indigo' => 'bg-indigo-100 text-indigo-700 border-indigo-200',
        ][$color] ?? 'bg-gray-100 text-gray-600 border-gray-200';

        $badgeDot = fn (?string $color) => [
            'gray' => 'bg-gray-400', 'success' => 'bg-green-500', 'warning' => 'bg-amber-500',
            'danger' => 'bg-red-500', 'info' => 'bg-blue-500', 'primary' => 'bg-brand-500', 'indigo' => 'bg-indigo-500',
        ][$color] ?? 'bg-gray-400';
    @endphp

    <div class="space-y-6">
        <x-ui.page-header title="Rewards & Referrals" subtitle="Invite friends and earn together." />

        {{-- Referral hero --}}
        <div class="rounded-[var(--radius-card)] bg-gradient-to-br from-brand-50 to-brand-100 border border-brand-200 p-6 shadow-[var(--shadow-card)]">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-neutral-600">Your referral code</p>
                    <p class="tabular mt-1 text-3xl font-bold tracking-widest text-neutral-900">{{ $referralCode ?: '—' }}</p>
                    <p class="mt-2 max-w-md text-sm text-neutral-600">Share your code or link. When friends sign up and get verified, you both earn rewards.</p>
                </div>
                @if ($referralCode)
                    <div class="w-full max-w-md" x-data="{ copied: null }">
                        <label class="mb-1.5 block text-xs font-medium text-neutral-600">Shareable link</label>
                        <div class="flex items-stretch gap-2">
                            <code class="min-w-0 flex-1 truncate rounded-xl bg-white border border-brand-200 px-3 py-2.5 font-mono text-xs text-neutral-900">{{ $shareLink }}</code>
                            <button type="button"
                                x-on:click="navigator.clipboard.writeText(@js($shareLink)).then(() => { copied = 'link'; setTimeout(() => copied = null, 2000); })"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-white px-3 text-sm font-medium text-neutral-800 hover:bg-neutral-100">
                                <span x-text="copied === 'link' ? 'Copied' : 'Copy'">Copy</span>
                            </button>
                        </div>
                        <button type="button"
                            x-on:click="navigator.clipboard.writeText(@js($referralCode)).then(() => { copied = 'code'; setTimeout(() => copied = null, 2000); })"
                            class="mt-2 text-xs font-medium text-amber-700 hover:text-amber-800">
                            <span x-text="copied === 'code' ? 'Code copied!' : 'Copy code only'">Copy code only</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>

        {{-- Stats --}}
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="pp-card group flex items-center gap-4 p-5">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-brand-100 text-brand-600">
                    <x-heroicon-o-gift class="h-6 w-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">Rewards earned</p>
                    <p class="tabular mt-1 text-2xl font-bold tracking-tight text-neutral-800">{{ $rewardCount }}</p>
                </div>
            </div>
            <div class="pp-card group flex items-center gap-4 p-5">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-500">
                    <x-heroicon-o-user-group class="h-6 w-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">Friends referred</p>
                    <p class="tabular mt-1 text-2xl font-bold tracking-tight text-neutral-800">{{ $referralCount }}</p>
                </div>
            </div>
            <div class="pp-card group flex items-center gap-4 p-5">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-500">
                    <x-heroicon-o-sparkles class="h-6 w-6" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">Reward types</p>
                    <p class="tabular mt-1 text-2xl font-bold tracking-tight text-neutral-800">{{ count($totals) }}</p>
                </div>
            </div>
        </div>

        {{-- Total earned by asset --}}
        @if (count($totals))
            <x-ui.card title="Total earned by asset">
                <div class="flex flex-wrap gap-2">
                    @foreach ($totals as $t)
                        <span class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 px-3 py-2">
                            <span class="inline-grid shrink-0 place-items-center rounded-full font-bold text-white h-7 w-7 text-[10px] {{ $assetBg($t['symbol']) }}">{{ \Illuminate\Support\Str::substr($t['symbol'], 0, 4) }}</span>
                            <span class="tabular text-sm font-semibold text-neutral-900">{{ $t['formatted'] }}</span>
                        </span>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Reward history --}}
            <x-ui.card title="Reward history">
                @if (count($grants))
                    <div>
                        @foreach ($grants as $i => $g)
                            <div @class(['flex items-center gap-3 py-2.5', 'border-b border-neutral-100' => $i < count($grants) - 1])>
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600">
                                    <x-heroicon-o-gift class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium capitalize text-neutral-900">{{ $g['type'] }}</p>
                                    <p class="text-xs text-neutral-500">{{ $g['at_human'] }}</p>
                                </div>
                                <p class="tabular text-sm font-semibold text-emerald-600">+{{ $g['amount'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty-state icon="gift" title="No rewards yet"
                        description="Refer friends and complete activities to start earning." />
                @endif
            </x-ui.card>

            {{-- Referrals --}}
            <x-ui.card title="Your referrals">
                @if (count($referrals))
                    <div>
                        @foreach ($referrals as $i => $r)
                            <div @class(['flex items-center gap-3 py-2.5', 'border-b border-neutral-100' => $i < count($referrals) - 1])>
                                <span class="inline-grid shrink-0 place-items-center rounded-full font-semibold text-white h-10 w-10 text-sm"
                                    style="background: hsl({{ $avatarHue($r['name']) }} 60% 45%);">{{ $initials($r['name']) }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium text-neutral-900">{{ $r['name'] }}</p>
                                    <p class="text-xs text-neutral-500">{{ $r['at_human'] }}</p>
                                </div>
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide {{ $badgeClass($r['status_color']) }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $badgeDot($r['status_color']) }}"></span>
                                    {{ $r['status_label'] }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <x-ui.empty-state icon="user-group" title="No referrals yet"
                        description="Share your link to invite friends to PoisaPay." />
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
