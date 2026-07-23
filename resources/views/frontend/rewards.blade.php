<x-layouts.app :title="__('Rewards & Referrals')">
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

        $shareMsg = __('Join me on PoisaPay — sign up with my link and we both earn rewards!');
        $waUrl = $shareLink ? 'https://wa.me/?text='.urlencode($shareMsg.' '.$shareLink) : null;
        $tgUrl = $shareLink ? 'https://t.me/share/url?url='.urlencode($shareLink).'&text='.urlencode($shareMsg) : null;
    @endphp

    <div class="space-y-6">
        <x-ui.page-header :title="__('Rewards & Referrals')" :subtitle="__('Invite friends and earn together.')" />

        {{-- Referral hero --}}
        <div class="relative overflow-hidden rounded-[var(--radius-card)] bg-gradient-to-br from-brand-500 via-brand-600 to-brand-800 p-6 text-white shadow-[var(--shadow-card)] sm:p-8"
            x-data="{
                copied: null,
                copy(text, tag) { navigator.clipboard.writeText(text).then(() => { this.copied = tag; setTimeout(() => this.copied = null, 2000); }); },
                nativeShare() {
                    if (navigator.share) { navigator.share({ title: 'PoisaPay', text: @js($shareMsg), url: @js($shareLink) }).catch(() => {}); }
                    else { this.copy(@js($shareLink), 'link'); }
                },
            }">
            <div class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-20 -left-10 h-52 w-52 rounded-full bg-white/5"></div>
            <x-heroicon-s-gift class="pointer-events-none absolute -bottom-6 right-4 h-40 w-40 text-white/5" />

            <div class="relative max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-wider text-white/70">{{ __('Referral program') }}</p>
                <h2 class="mt-1 text-2xl font-bold tracking-tight sm:text-3xl">{{ __('Invite friends, earn together') }}</h2>
                <p class="mt-2 max-w-md text-sm text-white/80">{{ __('Share your code or link. When friends sign up and get verified, you both earn rewards.') }}</p>

                @if ($referralCode)
                    {{-- Code + copy --}}
                    <div class="mt-5">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-white/60">{{ __('Your referral code') }}</p>
                        <div class="mt-1.5 flex items-center gap-2">
                            <span class="tabular rounded-xl bg-white/15 px-4 py-2.5 text-2xl font-bold tracking-[0.2em] ring-1 ring-white/20">{{ $referralCode }}</span>
                            <button type="button" x-on:click="copy(@js($referralCode), 'code')"
                                class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-white/15 ring-1 ring-white/20 transition hover:bg-white/25"
                                :title="copied === 'code' ? @js(__('Copied!')) : @js(__('Copy code'))" aria-label="{{ __('Copy code') }}">
                                <x-heroicon-o-clipboard-document x-show="copied !== 'code'" class="h-5 w-5" />
                                <x-heroicon-o-check x-show="copied === 'code'" x-cloak class="h-5 w-5" />
                            </button>
                        </div>
                    </div>

                    {{-- Share actions --}}
                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <button type="button" x-on:click="copy(@js($shareLink), 'link')"
                            class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-semibold text-brand-700 shadow-sm transition hover:bg-white/90">
                            <x-heroicon-o-link class="h-4 w-4" />
                            <span x-text="copied === 'link' ? @js(__('Link copied!')) : @js(__('Copy link'))">{{ __('Copy link') }}</span>
                        </button>
                        <a href="{{ $waUrl }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/25">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.885-9.885 9.885M20.52 3.449C18.24 1.245 15.24 0 12.045 0 5.463 0 .104 5.334.101 11.892c0 2.096.549 4.14 1.595 5.945L0 24l6.335-1.652a12.062 12.062 0 005.71 1.447h.006c6.585 0 11.946-5.336 11.949-11.896 0-3.176-1.24-6.165-3.487-8.411z"/></svg>
                            {{ __('WhatsApp') }}
                        </a>
                        <a href="{{ $tgUrl }}" target="_blank" rel="noopener"
                            class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/25">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg>
                            {{ __('Telegram') }}
                        </a>
                        <button type="button" x-on:click="nativeShare()"
                            class="inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm font-semibold ring-1 ring-white/20 transition hover:bg-white/25">
                            <x-heroicon-o-share class="h-4 w-4" /> {{ __('Share') }}
                        </button>
                    </div>
                @else
                    <p class="mt-5 inline-flex items-center gap-2 rounded-xl bg-white/15 px-4 py-2.5 text-sm ring-1 ring-white/20">
                        <x-heroicon-o-clock class="h-4 w-4" /> {{ __('Your referral code will appear here once your account is ready.') }}
                    </p>
                @endif
            </div>
        </div>

        {{-- How it works --}}
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['icon' => 'paper-airplane', 'title' => __('Share your link'), 'desc' => __('Send your referral link to friends and family.')],
                ['icon' => 'identification', 'title' => __('They join & verify'), 'desc' => __('Your friend signs up and completes verification.')],
                ['icon' => 'gift', 'title' => __('You both earn'), 'desc' => __('Rewards are credited to both wallets automatically.')],
            ] as $i => $step)
                <div class="pp-card flex items-start gap-3 p-5">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600">
                        <x-dynamic-component :component="'heroicon-o-'.$step['icon']" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-neutral-900">{{ $i + 1 }}. {{ $step['title'] }}</p>
                        <p class="mt-0.5 text-xs text-neutral-500">{{ $step['desc'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Stats --}}
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ([
                ['icon' => 'gift', 'bg' => 'bg-brand-100', 'fg' => 'text-brand-600', 'label' => __('Rewards earned'), 'value' => $rewardCount],
                ['icon' => 'user-group', 'bg' => 'bg-emerald-100', 'fg' => 'text-emerald-500', 'label' => __('Friends referred'), 'value' => $referralCount],
                ['icon' => 'sparkles', 'bg' => 'bg-amber-100', 'fg' => 'text-amber-500', 'label' => __('Reward types'), 'value' => count($totals)],
            ] as $stat)
                <div class="pp-card flex items-center gap-4 p-5">
                    <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl {{ $stat['bg'] }} {{ $stat['fg'] }}">
                        <x-dynamic-component :component="'heroicon-o-'.$stat['icon']" class="h-6 w-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $stat['label'] }}</p>
                        <p class="tabular mt-1 text-2xl font-bold tracking-tight text-neutral-800">{{ $stat['value'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Total earned by asset --}}
        @if (count($totals))
            <x-ui.card :title="__('Total earned by asset')">
                <div class="flex flex-wrap gap-2">
                    @foreach ($totals as $t)
                        <span class="inline-flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50/50 px-3 py-2">
                            <span class="inline-grid h-7 w-7 shrink-0 place-items-center rounded-full text-[10px] font-bold text-white {{ $assetBg($t['symbol']) }}">{{ \Illuminate\Support\Str::substr($t['symbol'], 0, 4) }}</span>
                            <span class="tabular text-sm font-semibold text-neutral-900">{{ $t['formatted'] }}</span>
                        </span>
                    @endforeach
                </div>
            </x-ui.card>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Reward history --}}
            <x-ui.card :title="__('Reward history')">
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
                    <x-ui.empty-state icon="gift" :title="__('No rewards yet')"
                        :description="__('Refer friends and complete activities to start earning.')" />
                @endif
            </x-ui.card>

            {{-- Referrals --}}
            <x-ui.card :title="__('Your referrals')">
                @if (count($referrals))
                    <div>
                        @foreach ($referrals as $i => $r)
                            <div @class(['flex items-center gap-3 py-2.5', 'border-b border-neutral-100' => $i < count($referrals) - 1])>
                                <span class="inline-grid h-10 w-10 shrink-0 place-items-center rounded-full text-sm font-semibold text-white"
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
                    <x-ui.empty-state icon="user-group" :title="__('No referrals yet')"
                        :description="__('Share your link to invite friends to PoisaPay.')" />
                @endif
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
