<x-layouts.app :title="'Cards'">
    <div class="space-y-6">
        <x-ui.page-header title="Cards" subtitle="Spend your balance anywhere with a PoisaPay card.">
            @if ($canIssue && $canCreate)
                <x-slot:actions>
                    <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'new-card')">New card</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        {{-- Verification gate --}}
        @unless ($canIssue)
            <x-ui.alert type="warning" title="Full verification required">
                Cards are only available to fully verified accounts. Complete identity verification to unlock virtual and physical cards.
            </x-ui.alert>
            <x-ui.card>
                <x-ui.empty-state icon="credit-card" title="No cards yet"
                    description="Once you're fully verified, you can create a card instantly.">
                    <x-slot:action>
                        <a href="{{ route('settings', ['tab' => 'verification']) }}">
                            <x-ui.button icon="identification">Verify my identity</x-ui.button>
                        </a>
                    </x-slot:action>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            @unless ($canCreate)
                <x-ui.alert type="warning">Card issuance is temporarily unavailable. Please check back soon.</x-ui.alert>
            @endunless

            @if ($cards->isEmpty())
                {{-- Hero empty state --}}
                <x-ui.card>
                    <x-ui.empty-state icon="credit-card" title="Create your first card"
                        description="A virtual or physical card, spendable instantly from your balance. Freeze, set limits, and manage it any time.">
                        @if ($canCreate)
                            <x-slot:action>
                                <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'new-card')">Create a card</x-ui.button>
                            </x-slot:action>
                        @endif
                    </x-ui.empty-state>
                </x-ui.card>
            @else
                {{-- Portfolio summary --}}
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        ['label' => 'Spent this month', 'value' => $monthCurrency.' '.$monthSpent, 'icon' => 'banknotes', 'bg' => 'bg-brand-100', 'fg' => 'text-brand-600'],
                        ['label' => 'Total cards', 'value' => $cards->count(), 'icon' => 'credit-card', 'bg' => 'bg-emerald-100', 'fg' => 'text-emerald-500'],
                        ['label' => 'Active cards', 'value' => $activeCount, 'icon' => 'check-circle', 'bg' => 'bg-amber-100', 'fg' => 'text-amber-500'],
                    ] as $stat)
                        <div class="pp-card group flex items-center gap-4 p-5 transition hover:-translate-y-0.5 hover:shadow-[var(--shadow-pop)]">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg {{ $stat['bg'] }} {{ $stat['fg'] }}">
                                <x-dynamic-component :component="'heroicon-o-'.$stat['icon']" class="h-6 w-6" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ $stat['label'] }}</p>
                                <p class="tabular mt-1 text-2xl font-bold tracking-tight text-neutral-800">{{ $stat['value'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($cards as $card)
                        @php
                            $isFrozen = $card->status === \App\Enums\CardStatus::Frozen;
                            $isClosed = $card->status === \App\Enums\CardStatus::Closed;
                            $isInactive = $card->status === \App\Enums\CardStatus::Inactive;
                            $visualFx = $isClosed ? 'opacity-60 grayscale' : ($isFrozen ? 'grayscale-[.6]' : ($isInactive ? 'saturate-[.6]' : ''));
                            $statusDot = ['success' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'danger' => 'bg-rose-500', 'gray' => 'bg-gray-400'][$card->status->color()] ?? 'bg-gray-400';
                            $expiry = $card->exp_month ? sprintf('%02d/%02d', $card->exp_month, $card->exp_year % 100) : '••/••';
                        @endphp
                        <div class="transition hover:-translate-y-0.5">
                            {{-- Card visual --}}
                            <div class="relative aspect-[1.586/1] overflow-hidden rounded-2xl bg-gradient-to-br from-brand-500 via-brand-600 to-brand-800 p-5 text-white shadow-[var(--shadow-card)] transition {{ $visualFx }}">
                                <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 80% 10%, black 1px, transparent 1px); background-size: 28px 28px;"></div>

                                @if ($isFrozen || $isClosed)
                                    <div class="absolute inset-0 z-10 grid place-items-center bg-ink-900/10 backdrop-blur-[1px]">
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/85 px-3 py-1 text-xs font-semibold text-ink-900 shadow-sm">
                                            <x-dynamic-component :component="$isFrozen ? 'heroicon-o-lock-closed' : 'heroicon-o-x-circle'" class="h-4 w-4" />
                                            {{ $card->status->label() }}
                                        </span>
                                    </div>
                                @endif
                                <div class="relative flex h-full flex-col justify-between">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-center gap-2">
                                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor"><path d="M14.615 1.595a.75.75 0 0 1 .359.852L12.982 9.75h7.268a.75.75 0 0 1 .548 1.262l-10.5 11.25a.75.75 0 0 1-1.272-.71l1.992-7.302H3.75a.75.75 0 0 1-.548-1.262l10.5-11.25a.75.75 0 0 1 .913-.143Z"/></svg>
                                            <span class="text-sm font-semibold">PoisaPay</span>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/85 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink-900 shadow-sm">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>{{ $card->status->label() }}
                                        </span>
                                    </div>

                                    {{-- EMV chip --}}
                                    <div class="h-7 w-9 rounded-md bg-gradient-to-br from-yellow-100 to-amber-300 shadow-inner ring-1 ring-ink-900/10"></div>

                                    <div>
                                        <p class="tabular font-mono text-lg tracking-widest">•••• •••• •••• {{ $card->last4 }}</p>
                                        <div class="mt-3 flex items-end justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-[9px] uppercase tracking-wider text-white/50">Card holder</p>
                                                <p class="truncate text-xs font-medium uppercase tracking-wide text-white/90">{{ $card->nickname ?: $holderName }}</p>
                                            </div>
                                            <div class="shrink-0 text-center">
                                                <p class="text-[9px] uppercase tracking-wider text-white/50">Valid thru</p>
                                                <p class="tabular text-xs font-medium text-white/90">{{ $expiry }}</p>
                                            </div>
                                            <span class="shrink-0 text-lg font-bold italic">{{ $card->network->label() }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- Controls --}}
                            <div class="mt-3 flex items-center gap-2">
                                @if ($card->status === \App\Enums\CardStatus::Inactive)
                                    <form method="POST" action="{{ route('cards.activate', $card->id) }}" class="flex-1">
                                        @csrf
                                        <x-ui.button type="submit" variant="success" size="sm" icon="power" class="w-full">Activate</x-ui.button>
                                    </form>
                                @elseif ($card->status === \App\Enums\CardStatus::Active)
                                    <form method="POST" action="{{ route('cards.freeze', $card->id) }}" class="flex-1">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary" size="sm" icon="lock-closed" class="w-full">Freeze</x-ui.button>
                                    </form>
                                @elseif ($card->status === \App\Enums\CardStatus::Frozen)
                                    <form method="POST" action="{{ route('cards.freeze', $card->id) }}" class="flex-1">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary" size="sm" icon="lock-open" class="w-full">Unfreeze</x-ui.button>
                                    </form>
                                @else
                                    <x-ui.button variant="ghost" size="sm" class="flex-1" disabled>Closed</x-ui.button>
                                @endif
                                <a href="{{ route('cards.manage', $card->id) }}" class="flex-1">
                                    <x-ui.button variant="secondary" size="sm" icon="cog-6-tooth" class="w-full">Manage</x-ui.button>
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Create-card modal (segmented type selector; provider is chosen server-side) --}}
            @if ($canCreate)
                <x-ui.modal name="new-card" title="Create a card" subtitle="Spendable instantly from your balance." maxWidth="sm">
                    <form id="new-card-form" method="POST" action="{{ route('cards.generate') }}" x-data="{ type: 'virtual' }">
                        @csrf
                        <input type="hidden" name="cardType" :value="type">

                        <p class="mb-2 text-sm font-medium text-slate-700">Card type</p>
                        <div class="grid {{ $supportsPhysical ? 'grid-cols-2' : 'grid-cols-1' }} gap-2.5">
                            <button type="button" x-on:click="type = 'virtual'"
                                :class="type === 'virtual' ? 'border-slate-900 ring-1 ring-slate-900 bg-slate-50' : 'border-slate-200 hover:border-slate-300'"
                                class="flex items-center gap-3 rounded-xl border p-3 text-left transition">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600"><x-heroicon-o-bolt class="h-5 w-5" /></span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900">Virtual</span>
                                    <span class="block text-xs text-slate-500">Instant, use online now</span>
                                </span>
                            </button>
                            @if ($supportsPhysical)
                                <button type="button" x-on:click="type = 'physical'"
                                    :class="type === 'physical' ? 'border-slate-900 ring-1 ring-slate-900 bg-slate-50' : 'border-slate-200 hover:border-slate-300'"
                                    class="flex items-center gap-3 rounded-xl border p-3 text-left transition">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-slate-100 text-slate-600"><x-heroicon-o-credit-card class="h-5 w-5" /></span>
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold text-slate-900">Physical</span>
                                        <span class="block text-xs text-slate-500">Shipped to you</span>
                                    </span>
                                </button>
                            @endif
                        </div>

                        @error('cardType')
                            <p class="mt-3 text-sm text-rose-600">{{ $message }}</p>
                        @enderror

                        {{-- What you get --}}
                        <div class="mt-4 space-y-2 rounded-xl bg-slate-50 p-3.5 ring-1 ring-slate-100">
                            @foreach ([
                                ['bolt', 'Spendable instantly from your balance'],
                                ['credit-card', $cardNetwork.' · settles in '.$settlementCurrency],
                                ['adjustments-horizontal', 'Limits '.$settlementCurrency.' 5,000/day · 2,000/transaction'],
                                ['lock-closed', 'Freeze, set a PIN & spend controls anytime'],
                            ] as [$ic, $txt])
                                <div class="flex items-center gap-2.5 text-xs text-slate-600">
                                    <x-dynamic-component :component="'heroicon-o-'.$ic" class="h-4 w-4 shrink-0 text-slate-400" />
                                    <span>{{ $txt }}</span>
                                </div>
                            @endforeach
                            <p class="pt-1 text-[11px] text-slate-400">New cards start inactive — activate from your Cards list to use.</p>
                        </div>
                    </form>

                    <x-slot:footer>
                        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'new-card')">Cancel</x-ui.button>
                        <x-ui.button type="submit" form="new-card-form" icon="plus">Create card</x-ui.button>
                    </x-slot:footer>
                </x-ui.modal>

                {{-- Reopen the modal if creation failed validation --}}
                @if ($errors->has('cardType'))
                    <div x-data x-init="$nextTick(() => $dispatch('open-modal', 'new-card'))"></div>
                @endif
            @endif
        @endunless
    </div>
</x-layouts.app>
