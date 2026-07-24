<x-layouts.app :title="__('Cards')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Cards')" :subtitle="__('Spend your balance anywhere with a PoisaPay card.')">
            @if ($canIssue && $canCreate)
                <x-slot:actions>
                    <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'new-card')">{{ __('New card') }}</x-ui.button>
                </x-slot:actions>
            @endif
        </x-ui.page-header>

        {{-- Verification gate --}}
        @unless ($canIssue)
            <x-ui.alert type="warning" :title="__('Full verification required')">
                {{ __('Cards are only available to fully verified accounts. Complete identity verification to unlock virtual and physical cards.') }}
            </x-ui.alert>
            <x-ui.card>
                <x-ui.empty-state icon="credit-card" :title="__('No cards yet')"
                    :description="__('Once you\'re fully verified, you can create a card instantly.')">
                    <x-slot:action>
                        <a href="{{ route('settings.index', ['tab' => 'verification']) }}">
                            <x-ui.button icon="identification">{{ __('Verify my identity') }}</x-ui.button>
                        </a>
                    </x-slot:action>
                </x-ui.empty-state>
            </x-ui.card>
        @else
            @unless ($canCreate)
                <x-ui.alert type="warning">{{ __('Card issuance is temporarily unavailable. Please check back soon.') }}</x-ui.alert>
            @endunless

            @if ($cards->isEmpty())
                {{-- Hero empty state --}}
                <x-ui.card>
                    <x-ui.empty-state icon="credit-card" :title="__('Create your first card')"
                        :description="__('A virtual or physical card, spendable instantly from your balance. Freeze, set limits, and manage it any time.')">
                        @if ($canCreate)
                            <x-slot:action>
                                <x-ui.button icon="plus" x-on:click="$dispatch('open-modal', 'new-card')">{{ __('Create a card') }}</x-ui.button>
                            </x-slot:action>
                        @endif
                    </x-ui.empty-state>
                </x-ui.card>
            @else
                {{-- Portfolio summary --}}
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    @foreach ([
                        ['label' => __('Spent this month'), 'value' => $monthCurrency.' '.$monthSpent, 'icon' => 'banknotes', 'fg' => 'text-neutral-900'],
                        ['label' => __('Transactions'), 'value' => number_format($monthTxCount), 'icon' => 'arrows-right-left', 'fg' => 'text-neutral-900'],
                        ['label' => __('Total cards'), 'value' => $cards->count(), 'icon' => 'credit-card', 'fg' => 'text-neutral-900'],
                        ['label' => __('Active'), 'value' => $activeCount, 'icon' => 'check-circle', 'fg' => $activeCount > 0 ? 'text-emerald-600' : 'text-neutral-900'],
                    ] as $stat)
                        <div class="pp-card flex items-center gap-3 p-4">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-500">
                                <x-dynamic-component :component="'heroicon-o-'.$stat['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-[11px] font-medium uppercase tracking-wide text-neutral-500">{{ $stat['label'] }}</p>
                                <p class="tabular truncate text-lg font-bold {{ $stat['fg'] }}">{{ $stat['value'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($cards as $card)
                        @php
                            $isFrozen = $card->status === \App\Enums\CardStatus::Frozen;
                            $isClosed = $card->status === \App\Enums\CardStatus::Closed;
                            $isInactive = $card->status === \App\Enums\CardStatus::Inactive;
                            $isPhysical = $card->type === \App\Enums\CardType::Physical;
                            $visualFx = $isClosed ? 'opacity-60 grayscale' : ($isFrozen ? 'grayscale-[.6]' : ($isInactive ? 'saturate-[.7]' : ''));
                            // Physical cards get a premium dark-metal finish; virtual keeps the brand gradient.
                            $gradient = $isPhysical
                                ? 'from-slate-700 via-slate-800 to-slate-950'
                                : 'from-brand-500 via-brand-600 to-brand-800';
                            $statusDot = ['success' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'danger' => 'bg-rose-500', 'gray' => 'bg-gray-400'][$card->status->color()] ?? 'bg-gray-400';
                            $expiry = $card->exp_month ? sprintf('%02d/%02d', $card->exp_month, $card->exp_year % 100) : '••/••';
                            $spend = $spendByCard[$card->id] ?? null;
                        @endphp
                        <div>
                            {{-- Card visual — the whole art opens Manage --}}
                            <a href="{{ route('cards.manage', $card->id) }}"
                                class="group relative block aspect-[1.586/1] overflow-hidden rounded-2xl bg-gradient-to-br {{ $gradient }} p-5 text-white shadow-[var(--shadow-card)] outline-none transition duration-200 hover:-translate-y-1 hover:shadow-[var(--shadow-pop)] focus-visible:ring-2 focus-visible:ring-brand-400 focus-visible:ring-offset-2 {{ $visualFx }}"
                                aria-label="{{ __('Manage') }} {{ $card->displayName() }}">
                                <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 80% 10%, black 1px, transparent 1px); background-size: 28px 28px;"></div>
                                {{-- Sheen on hover --}}
                                <div class="pointer-events-none absolute -inset-x-10 -top-1/2 h-[200%] rotate-12 bg-white/10 opacity-0 blur-2xl transition duration-500 group-hover:translate-x-1/3 group-hover:opacity-100"></div>

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
                                            <span class="rounded-full bg-white/15 px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-white/90 ring-1 ring-inset ring-white/20">{{ $card->type->label() }}</span>
                                        </div>
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/85 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink-900 shadow-sm">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>{{ $card->status->label() }}
                                        </span>
                                    </div>

                                    {{-- EMV chip + contactless --}}
                                    <div class="flex items-center gap-2">
                                        <div class="h-7 w-9 rounded-md bg-gradient-to-br from-yellow-100 to-amber-300 shadow-inner ring-1 ring-ink-900/10"></div>
                                        <svg class="h-4 w-4 text-white/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 8.5a8 8 0 0 1 0 7M9.5 6a12 12 0 0 1 0 12M13 4a15 15 0 0 1 0 16"/></svg>
                                    </div>

                                    <div>
                                        <p class="tabular font-mono text-lg tracking-widest">•••• •••• •••• {{ $card->last4 }}</p>
                                        <div class="mt-3 flex items-end justify-between gap-2">
                                            <div class="min-w-0">
                                                <p class="text-[9px] uppercase tracking-wider text-white/50">{{ __('Card holder') }}</p>
                                                <p class="truncate text-xs font-medium uppercase tracking-wide text-white/90">{{ $card->nickname ?: $holderName }}</p>
                                            </div>
                                            <div class="shrink-0 text-center">
                                                <p class="text-[9px] uppercase tracking-wider text-white/50">{{ __('Valid thru') }}</p>
                                                <p class="tabular text-xs font-medium text-white/90">{{ $expiry }}</p>
                                            </div>
                                            <span class="shrink-0"><x-ui.card-network-mark :network="$card->network->value" /></span>
                                        </div>
                                    </div>
                                </div>
                            </a>

                            {{-- Spend this month --}}
                            @if ($spend)
                                <div class="mt-3 flex items-center justify-between px-1 text-xs">
                                    <span class="text-neutral-500">{{ __('This month') }}</span>
                                    <span class="tabular font-medium text-neutral-700">{{ $spend['currency'] }} {{ $spend['spent'] }}
                                        <span class="text-neutral-400">· {{ trans_choice('{0}no transactions|{1}:count transaction|[2,*]:count transactions', $spend['count'], ['count' => $spend['count']]) }}</span>
                                    </span>
                                </div>
                            @endif

                            {{-- Controls --}}
                            <div class="mt-2.5 flex items-center gap-2">
                                @if ($card->status === \App\Enums\CardStatus::Inactive)
                                    <form method="POST" action="{{ route('cards.activate', $card->id) }}" class="flex-1">
                                        @csrf
                                        <x-ui.button type="submit" variant="success" size="sm" icon="power" class="w-full">{{ __('Activate') }}</x-ui.button>
                                    </form>
                                @elseif ($card->status === \App\Enums\CardStatus::Active)
                                    <form method="POST" action="{{ route('cards.freeze', $card->id) }}" class="flex-1">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary" size="sm" icon="lock-closed" class="w-full">{{ __('Freeze') }}</x-ui.button>
                                    </form>
                                @elseif ($card->status === \App\Enums\CardStatus::Frozen)
                                    <form method="POST" action="{{ route('cards.freeze', $card->id) }}" class="flex-1">
                                        @csrf
                                        <x-ui.button type="submit" variant="secondary" size="sm" icon="lock-open" class="w-full">{{ __('Unfreeze') }}</x-ui.button>
                                    </form>
                                @else
                                    <x-ui.button variant="ghost" size="sm" class="flex-1" disabled>{{ __('Closed') }}</x-ui.button>
                                @endif
                                <a href="{{ route('cards.manage', $card->id) }}" class="flex-1">
                                    <x-ui.button variant="secondary" size="sm" icon="cog-6-tooth" class="w-full">{{ __('Manage') }}</x-ui.button>
                                </a>
                            </div>
                        </div>
                    @endforeach

                    {{-- Add-card ghost tile --}}
                    @if ($canCreate)
                        <button type="button" x-on:click="$dispatch('open-modal', 'new-card')"
                            class="group flex aspect-[1.586/1] w-full flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-neutral-200 text-neutral-400 transition hover:border-brand-300 hover:bg-brand-50/40 hover:text-brand-600">
                            <span class="grid h-11 w-11 place-items-center rounded-full bg-neutral-100 transition group-hover:bg-brand-100">
                                <x-heroicon-o-plus class="h-6 w-6" />
                            </span>
                            <span class="text-sm font-medium">{{ __('New card') }}</span>
                        </button>
                    @endif
                </div>
            @endif

            {{-- Create-card modal (segmented type selector; provider is chosen server-side) --}}
            @if ($canCreate)
                <x-ui.modal name="new-card" :title="__('Create a card')" :subtitle="__('Spendable instantly from your balance.')" maxWidth="md">
                    <form id="new-card-form" method="POST" action="{{ route('cards.generate') }}"
                        x-data="{ type: 'virtual', prices: @js($pricing), get price() { return this.prices[this.type] ?? { label: '', free: true } } }">
                        @csrf
                        <input type="hidden" name="cardType" :value="type">

                        <p class="mb-2 text-sm font-medium text-slate-700">{{ __('Card type') }}</p>
                        <div class="grid {{ $supportsPhysical ? 'grid-cols-2' : 'grid-cols-1' }} gap-3">
                            @foreach ([
                                ['key' => 'virtual', 'icon' => 'bolt', 'title' => __('Virtual'), 'desc' => __('Instant · use online now'), 'price' => $pricing['virtual']],
                                ['key' => 'physical', 'icon' => 'credit-card', 'title' => __('Physical'), 'desc' => __('Shipped to your address'), 'price' => $pricing['physical']],
                            ] as $opt)
                                @if ($opt['key'] === 'virtual' || $supportsPhysical)
                                    <button type="button" x-on:click="type = '{{ $opt['key'] }}'"
                                        :class="type === '{{ $opt['key'] }}' ? 'border-brand-500 ring-2 ring-brand-500/20 bg-brand-50/30' : 'border-slate-200 hover:border-slate-300'"
                                        class="relative flex flex-col gap-3 rounded-2xl border p-4 text-left transition">
                                        {{-- Selected check --}}
                                        <span class="absolute right-3 top-3 grid h-5 w-5 place-items-center rounded-full border transition"
                                            :class="type === '{{ $opt['key'] }}' ? 'border-brand-500 bg-brand-500 text-white' : 'border-slate-300 text-transparent'">
                                            <x-heroicon-m-check class="h-3.5 w-3.5" />
                                        </span>
                                        <span class="grid h-10 w-10 place-items-center rounded-xl transition"
                                            :class="type === '{{ $opt['key'] }}' ? 'bg-brand-100 text-brand-600' : 'bg-slate-100 text-slate-500'">
                                            <x-dynamic-component :component="'heroicon-o-'.$opt['icon']" class="h-5 w-5" />
                                        </span>
                                        <span class="pr-6">
                                            <span class="block text-sm font-semibold text-slate-900">{{ $opt['title'] }}</span>
                                            <span class="mt-0.5 block text-xs text-slate-500">{{ $opt['desc'] }}</span>
                                        </span>
                                        <span class="inline-flex w-fit items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $opt['price']['free'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-900 text-white' }}">{{ $opt['price']['label'] }}</span>
                                    </button>
                                @endif
                            @endforeach
                        </div>

                        {{-- Price + your balance --}}
                        <div class="mt-3 flex items-center justify-between rounded-xl border border-slate-200 bg-white p-3.5">
                            <div>
                                <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('Card price') }}</p>
                                <p class="text-lg font-semibold text-slate-900" x-text="price.label"></p>
                            </div>
                            @if ($fundingAvailable !== null)
                                <div class="text-right">
                                    <p class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('Your balance') }}</p>
                                    <p class="tabular text-sm font-medium text-slate-700">{{ $fundingAvailable }}</p>
                                </div>
                            @endif
                        </div>
                        <p class="mt-1.5 text-[11px] text-slate-400" x-show="!price.free" x-cloak>{{ __('Charged once from your :sym balance when the card is created.', ['sym' => $fundingSymbol]) }}</p>

                        @error('cardType')
                            <p class="mt-3 text-sm text-rose-600">{{ $message }}</p>
                        @enderror

                        {{-- What you get --}}
                        <div class="mt-4 space-y-2 rounded-xl bg-slate-50 p-3.5 ring-1 ring-slate-100">
                            @foreach ([
                                ['bolt', __('Spendable instantly from your balance')],
                                ['credit-card', $cardNetwork.' · '.__('settles in').' '.$settlementCurrency],
                                ['adjustments-horizontal', __('Limits').' '.$settlementCurrency.' 5,000/day · 2,000/transaction'],
                                ['lock-closed', __('Freeze, set a PIN & spend controls anytime')],
                            ] as [$ic, $txt])
                                <div class="flex items-center gap-2.5 text-xs text-slate-600">
                                    <x-dynamic-component :component="'heroicon-o-'.$ic" class="h-4 w-4 shrink-0 text-slate-400" />
                                    <span>{{ $txt }}</span>
                                </div>
                            @endforeach
                            <p class="pt-1 text-[11px] text-slate-400">{{ __('New cards start inactive — activate from your Cards list to use.') }}</p>
                        </div>
                    </form>

                    <x-slot:footer>
                        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'new-card')">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button type="submit" form="new-card-form" icon="plus">{{ __('Create card') }}</x-ui.button>
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
