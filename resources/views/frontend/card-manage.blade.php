<x-layouts.app :title="__('Manage Card')">
    @php
        $badge = fn (string $color) => match ($color) {
            'success' => 'bg-emerald-50 text-emerald-700 border border-emerald-200',
            'warning' => 'bg-amber-50 text-amber-700 border border-amber-200',
            'danger' => 'bg-red-50 text-red-700 border border-red-200',
            default => 'bg-gray-100 text-gray-600 border border-gray-200',
        };

        // Status-aware card visual (mirrors the /cards list).
        $isFrozen = $card->status === \App\Enums\CardStatus::Frozen;
        $isClosed = $card->status === \App\Enums\CardStatus::Closed;
        $isInactive = $card->status === \App\Enums\CardStatus::Inactive;
        $visualFx = $isClosed ? 'opacity-60 grayscale' : ($isFrozen ? 'grayscale-[.6]' : ($isInactive ? 'saturate-[.6]' : ''));
        $statusDot = ['success' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'danger' => 'bg-rose-500', 'gray' => 'bg-gray-400'][$card->status->color()] ?? 'bg-gray-400';
        $expiry = $card->exp_month ? sprintf('%02d/%02d', $card->exp_month, $card->exp_year % 100) : '••/••';
    @endphp

    <div x-data="{ disputingAuthId: null }" class="space-y-6">
        <x-ui.page-header :title="__('Manage card')" :subtitle="__('Manage spend controls, security and statements for this card.')">
            <x-slot:actions>
                <a href="{{ route('cards') }}">
                    <x-ui.button variant="secondary" size="sm" icon="arrow-left">{{ __('Back to cards') }}</x-ui.button>
                </a>
            </x-slot:actions>
        </x-ui.page-header>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Left column --}}
            <div class="space-y-6 lg:col-span-1">
                {{-- Card visual --}}
                <div class="relative aspect-[1.586/1] overflow-hidden rounded-2xl bg-gradient-to-br from-brand-400 via-brand-600 to-brand-800 p-5 text-white shadow-[var(--shadow-card)] transition {{ $visualFx }}">
                    <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 80% 10%, black 1px, transparent 1px); background-size: 28px 28px;"></div>

                    {{-- Frozen / closed overlay makes an unusable card unmistakable --}}
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
                                    <p class="text-[9px] uppercase tracking-wider text-white/50">{{ __('Card holder') }}</p>
                                    <p class="truncate text-xs font-medium uppercase tracking-wide text-white/90">{{ $card->nickname ?: $holderName }}</p>
                                </div>
                                <div class="shrink-0 text-center">
                                    <p class="text-[9px] uppercase tracking-wider text-white/50">{{ __('Valid thru') }}</p>
                                    <p class="tabular text-xs font-medium text-white/90">{{ $expiry }}</p>
                                </div>
                                <span class="shrink-0 text-lg font-bold italic">{{ $card->network->label() }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Status / meta --}}
                <x-ui.card>
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">{{ __('Status') }}</dt>
                            <dd><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide {{ $badge($card->status->color()) }}">{{ $card->status->label() }}</span></dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">{{ __('Network') }}</dt>
                            <dd class="font-medium text-gray-900">{{ $card->network->label() }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">{{ __('Type') }}</dt>
                            <dd class="font-medium text-gray-900">{{ $card->type->label() }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">{{ __('Expiry') }}</dt>
                            <dd class="tabular font-medium text-gray-900">{{ $expiry }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-gray-500">{{ __('PIN') }}</dt>
                            <dd><span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide {{ $badge($card->hasPin() ? 'success' : 'gray') }}">{{ $card->hasPin() ? __('Set') : __('Not set') }}</span></dd>
                        </div>
                    </dl>
                </x-ui.card>

                {{-- Card details — revealed straight from the issuer into the user's browser
                     (PCI SAQ-A). Step-up guarded; PoisaPay's server never sees the PAN/CVV
                     for a live provider. See CardManageController::revealSession(). --}}
                <x-ui.card :title="__('Card details')" :subtitle="__('Sensitive data never touches PoisaPay servers.')">
                    @if ($canReveal)
                        <div
                            x-data="cardReveal({
                                driver: @js($revealDriver),
                                card: @js($issuerCardRef),
                                pk: @js($stripePublishableKey),
                                url: @js(route('card.reveal', $card->id)),
                                csrf: @js(csrf_token()),
                                last4: @js($card->last4),
                            })"
                            class="space-y-4"
                        >
                            {{-- Step-up: confirm password, then reveal --}}
                            <form x-show="!revealed" x-on:submit.prevent="submit" class="space-y-3">
                                <p class="text-xs text-gray-500">{{ __('Confirm your password to view full card details.') }}</p>
                                <input type="password" x-model="password" autocomplete="current-password"
                                    placeholder="{{ __('Account password') }}"
                                    class="w-full rounded-lg border-gray-300 text-sm focus:border-brand-500 focus:ring-brand-500" />
                                <p x-show="error" x-cloak x-text="error" class="text-xs font-medium text-red-600"></p>
                                <x-ui.button type="submit" variant="secondary" size="sm" class="w-full" x-bind:disabled="loading">
                                    <span x-text="loading ? @js(__('Loading…')) : @js(__('Reveal card details'))"></span>
                                </x-ui.button>
                            </form>

                            {{-- Revealed panel: Stripe mounts PCI iframes into the refs; the mock writes text --}}
                            <div x-show="revealed" x-cloak class="space-y-3 rounded-xl border border-gray-200 bg-white p-4">
                                <div>
                                    <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __('Card number') }}</p>
                                    <div x-ref="pan" class="font-mono text-lg tracking-widest text-gray-900">•••• •••• •••• {{ $card->last4 }}</div>
                                </div>
                                <div class="flex gap-6">
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __('Expiry') }}</p>
                                        <div x-ref="exp" class="font-mono text-gray-900">{{ $expiry }}</div>
                                    </div>
                                    <div>
                                        <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __('CVV') }}</p>
                                        <div x-ref="cvc" class="font-mono text-gray-900">•••</div>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between pt-1">
                                    <p class="text-[11px] text-gray-400" x-show="secondsLeft > 0">
                                        {{ __('Hides automatically in') }} <span x-text="secondsLeft"></span>s
                                    </p>
                                    <x-ui.button type="button" variant="ghost" size="sm" x-on:click="hide()">{{ __('Hide') }}</x-ui.button>
                                </div>
                            </div>

                            <x-ui.alert type="info">
                                @if ($revealDriver === 'mock')
                                    {{ __('Simulated provider: these demo details are generated locally. With a live issuer the real PAN, expiry and CVV render inside the issuer\'s PCI-DSS iframe and never touch PoisaPay servers.') }}
                                @else
                                    {{ __('The full PAN, expiry and CVV are rendered inside the issuer\'s PCI-DSS-compliant iframe directly in your browser. PoisaPay never sees or stores them — we retain only an opaque issuer token.') }}
                                @endif
                            </x-ui.alert>
                        </div>
                    @else
                        <div class="space-y-3">
                            <div>
                                <p class="text-[11px] uppercase tracking-wide text-gray-500">{{ __('Card number') }}</p>
                                <p class="font-mono text-lg tracking-widest text-gray-900">•••• •••• •••• {{ $card->last4 }}</p>
                            </div>
                            <x-ui.alert type="info">{{ __('Full card details are unavailable for this card.') }}</x-ui.alert>
                        </div>
                    @endif
                </x-ui.card>

                {{-- Actions --}}
                <x-ui.card :title="__('Card actions')">
                    <div class="space-y-2">
                        @if ($card->status !== \App\Enums\CardStatus::Closed)
                            <form method="POST" action="{{ route('card.freeze', $card->id) }}">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" size="sm" class="w-full">{{ $card->status === \App\Enums\CardStatus::Frozen ? __('Unfreeze card') : __('Freeze card') }}</x-ui.button>
                            </form>
                            <x-ui.button x-on:click="$dispatch('open-modal', 'replace-card')" variant="secondary" size="sm" icon="arrow-path" class="w-full">{{ __('Replace card') }}</x-ui.button>
                            <x-ui.button x-on:click="$dispatch('open-modal', 'close-card')" variant="danger" size="sm" icon="x-circle" class="w-full">{{ __('Close card') }}</x-ui.button>
                        @else
                            <x-ui.alert type="danger">{{ __('This card is closed and can no longer be used.') }}</x-ui.alert>
                        @endif
                    </div>
                </x-ui.card>
            </div>

            {{-- Right column --}}
            <div class="space-y-6 lg:col-span-2">
                {{-- Controls --}}
                <x-ui.card :title="__('Card nickname')" :subtitle="__('Give your card a name you\'ll recognise.')">
                    <form method="POST" action="{{ route('card.controls', $card->id) }}" class="space-y-5">
                        @csrf @method('PUT')
                        <x-ui.input :label="__('Nickname')" name="nickname" :value="old('nickname', $card->nickname)" :placeholder="__('e.g. Travel card')" :error="$errors->first('nickname')" />

                        {{-- Spend controls are hidden here; preserve their current values so
                             saving the nickname doesn't reset the card's controls. --}}
                        @if ($card->online_enabled)<input type="hidden" name="online_enabled" value="1">@endif
                        @if ($card->atm_enabled)<input type="hidden" name="atm_enabled" value="1">@endif
                        @if ($card->contactless_enabled)<input type="hidden" name="contactless_enabled" value="1">@endif
                        @if ($card->daily_limit)<input type="hidden" name="daily_limit" value="{{ number_format($card->daily_limit / 100, 2, '.', '') }}">@endif
                        @if ($card->per_tx_limit)<input type="hidden" name="per_tx_limit" value="{{ number_format($card->per_tx_limit / 100, 2, '.', '') }}">@endif
                        <input type="hidden" name="allowed_countries" value="{{ $card->allowed_countries ? implode(', ', $card->allowed_countries) : '' }}">
                        <input type="hidden" name="blocked_mccs" value="{{ $card->blocked_mccs ? implode(', ', $card->blocked_mccs) : '' }}">

                        <div class="flex justify-end">
                            <x-ui.button type="submit" icon="check">{{ __('Save') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.card>

                {{-- Set PIN --}}
                <x-ui.card :title="__('Card PIN')" :subtitle="__('Used for ATM and chip-and-PIN. We store only a secure hash.')">
                    <form method="POST" action="{{ route('card.pin', $card->id) }}" class="flex flex-col gap-3 sm:flex-row sm:items-end">
                        @csrf
                        <x-ui.input :label="__('New PIN (4-6 digits)')" name="pin" type="password" inputmode="numeric" placeholder="••••" class="sm:max-w-xs" :error="$errors->first('pin')" />
                        <x-ui.button type="submit" icon="key">{{ $card->hasPin() ? __('Change PIN') : __('Set PIN') }}</x-ui.button>
                    </form>
                </x-ui.card>

                {{-- Statement --}}
                <x-ui.card>
                    <x-slot:actions>
                        <form method="GET" action="{{ route('cards.manage', $card->id) }}">
                            <select name="month" onchange="this.form.submit()"
                                class="rounded-lg border-gray-300 !py-2 text-sm focus:border-brand-500 focus:ring-brand-500">
                                @foreach ($monthOptions as $m)
                                    <option value="{{ $m['value'] }}" @selected($selectedMonth === $m['value'])>{{ $m['label'] }}</option>
                                @endforeach
                            </select>
                        </form>
                    </x-slot:actions>
                    <div class="mb-4">
                        <h3 class="text-base font-semibold text-neutral-900">{{ __('Statement') }}</h3>
                        <p class="mt-0.5 text-sm text-neutral-500">{{ $statement['from'] }} → {{ $statement['to'] }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="rounded-xl border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Settled') }}</p>
                            <p class="tabular mt-1 text-2xl font-bold text-gray-900">{{ $currency }} {{ $statement['settled'] }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Refunded') }}</p>
                            <p class="tabular mt-1 text-2xl font-bold text-gray-900">{{ $currency }} {{ $statement['refunded'] }}</p>
                        </div>
                        <div class="rounded-xl border border-gray-200 p-4">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Transactions') }}</p>
                            <p class="tabular mt-1 text-2xl font-bold text-gray-900">{{ $statement['count'] }}</p>
                        </div>
                    </div>

                    @if (count($statement['byMcc']))
                        <div class="mt-5">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('Spend by category (MCC)') }}</p>
                            <x-ui.table :headers="[__('MCC'), __('Settled')]">
                                @foreach ($statement['byMcc'] as $row)
                                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                                        <td class="px-3 py-2.5 font-mono text-sm text-gray-700">{{ $row['mcc'] }}</td>
                                        <td class="px-3 py-2.5 tabular text-sm font-medium text-gray-900">{{ $currency }} {{ $row['amount'] }}</td>
                                    </tr>
                                @endforeach
                            </x-ui.table>
                        </div>
                    @else
                        <p class="mt-4 text-sm text-gray-500">{{ __('No settled spend for this period.') }}</p>
                    @endif
                </x-ui.card>

                {{-- Analytics --}}
                <x-ui.card :title="__('Spend analytics')" :subtitle="__('Settled spend over the last 6 months.')">
                    @if (! count($analytics))
                        <p class="text-sm text-gray-500">{{ __('No spend recorded yet.') }}</p>
                    @else
                        <div class="space-y-3">
                            @foreach ($analytics as $row)
                                <div>
                                    <div class="mb-1 flex items-center justify-between text-sm">
                                        <span class="font-medium text-gray-700">{{ $row['label'] }}</span>
                                        <span class="tabular text-gray-900">
                                            <span>{{ $currency }} {{ $row['amount'] }}</span>
                                            <span class="text-xs text-gray-400">· {{ $row['count'] }} {{ __('tx') }}</span>
                                        </span>
                                    </div>
                                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-100">
                                        <div class="h-full rounded-full bg-brand-500" style="width: {{ $row['pct'] }}%"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-ui.card>

                {{-- Recent authorizations --}}
                <x-ui.card :title="__('Recent authorizations')" :subtitle="__('Latest 20 authorizations on this card.')">
                    @if (! count($auths))
                        <x-ui.empty-state icon="banknotes" :title="__('No activity yet')" :description="__('Authorizations will appear here as the card is used.')" />
                    @else
                        <x-ui.table :headers="[__('Merchant'), __('MCC'), __('Amount'), __('Status'), __('Date'), '']">
                            @foreach ($auths as $a)
                                <tr class="border-b border-gray-200 hover:bg-gray-100">
                                    <td class="px-3 py-3 font-medium text-gray-900">{{ $a['merchant'] }}</td>
                                    <td class="px-3 py-3 font-mono text-xs text-gray-600">{{ $a['mcc'] }}</td>
                                    <td class="px-3 py-3 tabular text-sm text-gray-900">{{ $a['amount'] }}</td>
                                    <td class="px-3 py-3">
                                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide {{ $badge($a['statusColor']) }}">{{ $a['statusLabel'] }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-sm text-gray-500">{{ $a['date'] }}</td>
                                    <td class="px-3 py-3 text-right">
                                        @if ($a['disputable'])
                                            <x-ui.button variant="ghost" size="sm" icon="flag" x-on:click="disputingAuthId = {{ \Illuminate\Support\Js::from($a['id']) }}; $dispatch('open-modal', 'dispute-auth')">{{ __('Dispute') }}</x-ui.button>
                                        @elseif ($a['disputed'])
                                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase tracking-wide {{ $badge('warning') }}">{{ __('Disputed') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </x-ui.table>
                    @endif
                </x-ui.card>
            </div>
        </div>

        {{-- Replace modal --}}
        <x-ui.modal name="replace-card" :title="__('Replace card')" :subtitle="__('Closes this card and issues a fresh one with a new number.')" maxWidth="sm">
            <form id="replace-card-form" method="POST" action="{{ route('card.replace', $card->id) }}">
                @csrf
                <x-ui.select :label="__('Reason')" name="reason">
                    <option value="lost">{{ __('Lost') }}</option>
                    <option value="stolen">{{ __('Stolen') }}</option>
                    <option value="damaged">{{ __('Damaged') }}</option>
                </x-ui.select>
                <p class="mt-3 text-xs text-slate-500">{{ __('Existing authorizations are unaffected.') }}</p>
            </form>
            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'replace-card')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="replace-card-form" icon="arrow-path">{{ __('Replace card') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>

        {{-- Close modal --}}
        <x-ui.confirmation-modal name="close-card" maxWidth="sm">
            <x-slot:title>{{ __('Close this card?') }}</x-slot:title>
            <x-slot:content>{{ __("Closing is permanent — the card can no longer be used. Cards with pending approved authorizations can't be closed.") }}</x-slot:content>
            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'close-card')">{{ __('Cancel') }}</x-ui.button>
                <form method="POST" action="{{ route('card.close', $card->id) }}">
                    @csrf
                    <x-ui.button type="submit" variant="danger" icon="x-circle">{{ __('Close card') }}</x-ui.button>
                </form>
            </x-slot:footer>
        </x-ui.confirmation-modal>

        {{-- Dispute modal --}}
        <x-ui.modal name="dispute-auth" :title="__('Dispute purchase')" :subtitle="__('Opens a chargeback case; funds move only when it resolves.')" maxWidth="sm">
            <form id="dispute-form" method="POST" action="{{ route('card.dispute', $card->id) }}">
                @csrf
                <input type="hidden" name="authId" :value="disputingAuthId" />
                <x-ui.select :label="__('Reason')" name="reason">
                    <option value="fraud">{{ __('Fraudulent charge') }}</option>
                    <option value="not_received">{{ __('Goods / service not received') }}</option>
                    <option value="duplicate">{{ __('Duplicate charge') }}</option>
                    <option value="incorrect_amount">{{ __('Incorrect amount') }}</option>
                </x-ui.select>
            </form>
            <x-slot:footer>
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'dispute-auth')">{{ __('Cancel') }}</x-ui.button>
                <x-ui.button type="submit" form="dispute-form" icon="flag">{{ __('Open dispute') }}</x-ui.button>
            </x-slot:footer>
        </x-ui.modal>
    </div>
</x-layouts.app>
