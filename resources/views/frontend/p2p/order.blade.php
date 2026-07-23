<x-layouts.app :title="__('Order :ref', ['ref' => $order->ref])">
    @php
        $s = $order->status->value;
        $done = in_array($s, ['completed', 'force_released']);
        $failed = in_array($s, ['cancelled', 'expired', 'force_cancelled']);
        $disputed = $s === 'disputed';
        $cp = $isBuyer ? $order->seller : $order->buyer;
        $cpVerified = $cp->kyc_tier === \App\Enums\KycTier::Full;

        // Happy-path progress tracker (4 nodes). $current = index of the in-progress
        // node, or 4 when everything is done.
        $steps = [
            ['label' => __('Order created'), 'desc' => __('Escrow locked')],
            ['label' => $isBuyer ? __('Payment sent') : __('Payment received'), 'desc' => __('Fiat transferred')],
            ['label' => __('Release'), 'desc' => __('Seller confirms')],
            ['label' => __('Completed'), 'desc' => __('Crypto delivered')],
        ];
        $n = count($steps);
        $current = match ($s) {
            'waiting_payment' => 1,
            'buyer_paid' => 2,
            'releasing', 'disputed' => 2,
            'completed', 'force_released', 'refunded' => $n,
            default => 1, // cancelled / expired / force_cancelled — failed at payment
        };
        $greenSteps = $done ? $n - 1 : ($failed ? max(0, $current - 1) : min($current, $n - 1));
        $greenPct = ($greenSteps / ($n - 1)) * 75;

        // Contextual headline for the status hero.
        $headline = match (true) {
            $done => __('Order completed'),
            $failed => $order->status->label(),
            $disputed => __('Under dispute'),
            $s === 'waiting_payment' && $isBuyer => __('Pay the seller'),
            $s === 'waiting_payment' => __('Waiting for the buyer to pay'),
            in_array($s, ['buyer_paid', 'releasing']) && ! $isBuyer => __('Confirm payment & release'),
            default => __('Waiting for the seller to release'),
        };
    @endphp

    <div class="space-y-6">
        <x-ui.page-header :title="__('Order :ref', ['ref' => $order->ref])" :subtitle="__('Crypto is escrowed on-ledger; settle the fiat leg with your counterparty and confirm.')">
            <x-slot:actions>
                <a href="{{ route('p2p.orders') }}"><x-ui.button variant="secondary" icon="arrow-left">{{ __('My orders') }}</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif
        @if (session('error'))<x-ui.alert type="error">{{ session('error') }}</x-ui.alert>@endif

        {{-- ─── Status hero + progress tracker ─── --}}
        <x-ui.card @class([
            'border-l-4',
            'border-l-green-500' => $done,
            'border-l-red-500' => $failed,
            'border-l-amber-400' => $disputed,
            'border-l-brand-500' => ! $done && ! $failed && ! $disputed,
        ])>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="flex items-start gap-3">
                    <span @class([
                        'grid h-11 w-11 shrink-0 place-items-center rounded-full',
                        'bg-green-100 text-green-600' => $done,
                        'bg-red-100 text-red-600' => $failed,
                        'bg-amber-100 text-amber-600' => $disputed,
                        'bg-brand-100 text-brand-600' => ! $done && ! $failed && ! $disputed,
                    ])>
                        @if ($done)<x-heroicon-s-check-circle class="h-6 w-6" />
                        @elseif ($failed)<x-heroicon-s-x-circle class="h-6 w-6" />
                        @elseif ($disputed)<x-heroicon-s-scale class="h-6 w-6" />
                        @else<x-heroicon-s-clock class="h-6 w-6" />
                        @endif
                    </span>
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-lg font-bold text-neutral-900">{{ $headline }}</h2>
                            <span class="rounded px-2 py-0.5 text-xs font-bold uppercase tracking-wide {{ $isBuyer ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $isBuyer ? __('Buy') : __('Sell') }}</span>
                        </div>
                        <p class="mt-0.5 flex items-center gap-1.5 text-sm text-neutral-500">
                            <x-ui.badge :color="$order->status->color()" dot>{{ $order->status->label() }}</x-ui.badge>
                            <span class="text-neutral-300">·</span>
                            {{ $order->cryptoMoney()->format() }}
                        </p>
                    </div>
                </div>

                @if ($s === 'waiting_payment' && $order->expires_at)
                    <div x-data="{ left: '', urgent: false }"
                         x-init="const end = {{ $order->expires_at->timestamp }} * 1000;
                             const t = () => { let d = Math.max(0, Math.floor((end - Date.now())/1000)); urgent = d <= 120;
                                 left = String(Math.floor(d/60)).padStart(2,'0') + ':' + String(d%60).padStart(2,'0'); };
                             t(); setInterval(t, 1000);"
                         class="flex items-center gap-2 rounded-full px-3.5 py-2 text-sm font-medium transition-colors"
                         :class="urgent ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-800'">
                        <x-heroicon-o-clock class="h-4 w-4 shrink-0" x-bind:class="urgent ? 'animate-pulse' : ''" />
                        <span>{{ __('Pay within') }}</span>
                        <span class="text-base font-bold tabular" x-text="left"></span>
                    </div>
                @endif
            </div>

            {{-- Stepper --}}
            <div class="relative mt-8 pb-1">
                <span class="absolute left-[12.5%] right-[12.5%] top-4 h-0.5 rounded bg-neutral-200"></span>
                <span class="absolute left-[12.5%] top-4 h-0.5 rounded {{ $failed ? 'bg-red-400' : 'bg-green-500' }} transition-all" style="width: {{ $greenPct }}%"></span>
                <ol class="relative flex">
                    @foreach ($steps as $i => $step)
                        @php
                            $isDone = $i < $current;
                            $isFail = $failed && $i === $current;
                            $isCurrent = ! $failed && ! $done && $i === $current;
                        @endphp
                        <li class="flex flex-1 flex-col items-center text-center">
                            <span @class([
                                'grid h-8 w-8 place-items-center rounded-full text-sm font-semibold ring-4 ring-white',
                                'bg-green-500 text-white' => $isDone,
                                'bg-red-500 text-white' => $isFail,
                                'bg-brand-500 text-white pp-pulse' => $isCurrent,
                                'border border-neutral-300 bg-white text-neutral-400' => ! $isDone && ! $isFail && ! $isCurrent,
                            ])>
                                @if ($isDone)<x-heroicon-s-check class="h-4 w-4" />
                                @elseif ($isFail)<x-heroicon-s-x-mark class="h-4 w-4" />
                                @else {{ $i + 1 }}
                                @endif
                            </span>
                            <span class="mt-2 text-xs font-semibold {{ $isCurrent ? 'text-brand-700' : ($isDone ? 'text-neutral-900' : 'text-neutral-500') }}">{{ $step['label'] }}</span>
                            <span class="hidden text-[0.7rem] text-neutral-400 sm:block">{{ $step['desc'] }}</span>
                        </li>
                    @endforeach
                </ol>
            </div>
        </x-ui.card>

        <div class="grid gap-6 lg:grid-cols-[1.1fr_1fr]">
            {{-- ─── Trade details + actions ─── --}}
            <div class="space-y-6">
                <x-ui.card>
                    {{-- Amount hero --}}
                    <div class="rounded-2xl border p-5 {{ $isBuyer ? 'border-green-100 bg-green-50/60' : 'border-red-100 bg-red-50/60' }}">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs font-medium text-neutral-500">{{ $isBuyer ? __('You buy') : __('You sell') }}</p>
                                <p class="mt-1 text-2xl font-bold tabular text-neutral-900">{{ $order->cryptoMoney()->format() }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-medium text-neutral-500">{{ $isBuyer ? __('You pay') : __('You receive') }}</p>
                                <p class="mt-1 text-2xl font-bold tabular text-neutral-900">{{ number_format((float) $order->fiat_amount, 2) }} <span class="text-sm font-medium text-neutral-400">{{ $order->fiat_currency }}</span></p>
                            </div>
                        </div>
                    </div>

                    <x-ui.list-group class="mt-4 divide-y divide-neutral-100">
                        <x-ui.list-group-item :label="__('Order number')" class="!py-2.5">
                            <x-slot:value>
                                <span class="font-mono text-xs">{{ $order->ref }}</span> <x-ui.copy-text :text="$order->ref" />
                            </x-slot:value>
                        </x-ui.list-group-item>
                        <x-ui.list-group-item :label="__('Price')" :value="number_format((float) $order->price, 4).' '.$order->fiat_currency" class="!py-2.5" />
                        @if ($isBuyer)
                            <x-ui.list-group-item :label="__('Fee / you receive')" class="!py-2.5">
                                <x-slot:value>{{ $order->feeMoney()->format() }} · <span class="font-semibold text-neutral-900">{{ $order->netMoney()->format() }}</span></x-slot:value>
                            </x-ui.list-group-item>
                        @endif
                        <x-ui.list-group-item :label="__('Counterparty')" class="!py-2.5">
                            <x-slot:value>
                                <a href="{{ route('p2p.merchant', $cp->getKey()) }}" class="flex items-center gap-2 hover:text-brand-600">
                                    <x-ui.avatar :name="$cp->name" size="sm" />
                                    <span class="font-medium text-neutral-900">{{ $cp->name }}</span>
                                    @if ($cpVerified)<x-heroicon-s-check-badge class="h-4 w-4 text-brand-500" />@endif
                                </a>
                            </x-slot:value>
                        </x-ui.list-group-item>
                        @if ($order->paymentMethod)
                            <x-ui.list-group-item :label="__('Payment method')" class="!py-2.5">
                                <x-slot:value><span class="inline-flex items-center rounded-md border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-600">{{ $order->paymentMethod->name }}</span></x-slot:value>
                            </x-ui.list-group-item>
                        @endif
                        <x-ui.list-group-item :label="__('Opened')" :value="$order->created_at?->format('d M, Y · h:i A')" class="!py-2.5" />
                    </x-ui.list-group>
                </x-ui.card>

                {{-- What to do now --}}
                @if ($disputed)
                    <x-ui.alert type="warning" :title="__('Under dispute')">{{ __('An operator is reviewing the evidence. Add anything that supports your case in the dispute panel below.') }}</x-ui.alert>
                @elseif ($order->status->isOpen())
                    <div class="rounded-2xl border border-brand-100 bg-brand-50/70 p-4">
                        <div class="flex items-start gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-500 text-white">
                                <x-heroicon-o-information-circle class="h-5 w-5" />
                            </span>
                            <div class="text-sm">
                                <p class="font-semibold text-neutral-900">
                                    @if ($s === 'waiting_payment' && $isBuyer) {{ __('Send the payment') }}
                                    @elseif ($s === 'waiting_payment') {{ __('Waiting for the buyer') }}
                                    @elseif (in_array($s, ['buyer_paid', 'releasing']) && ! $isBuyer) {{ __('Confirm you received the fiat') }}
                                    @else {{ __('Waiting for the seller to release') }}
                                    @endif
                                </p>
                                <p class="mt-0.5 text-neutral-600">
                                    @if ($s === 'waiting_payment' && $isBuyer)
                                        {{ __("Pay the seller off-platform with the agreed method, then tap \"I've paid\". Ask for their account details in chat.") }}
                                    @elseif ($s === 'waiting_payment')
                                        {{ __("The buyer is sending the fiat. Release the USDT only once you've confirmed it arrived in your account.") }}
                                    @elseif (in_array($s, ['buyer_paid', 'releasing']) && ! $isBuyer)
                                        {{ __('The buyer marked this paid. Verify the money landed, then release the escrow. Open a dispute if it never arrives.') }}
                                    @else
                                        {{ __("You've marked this paid. The seller is verifying and will release your USDT shortly.") }}
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @elseif ($done)
                    <x-ui.alert type="success" :title="__('Trade complete')">{{ $isBuyer ? __('The escrow was released — the USDT is in your wallet') : __('The escrow was released — the buyer received the USDT') }}.</x-ui.alert>
                @else
                    <x-ui.alert type="info">{{ __('This order is :status — no further action needed.', ['status' => $order->status->label()]) }}</x-ui.alert>
                @endif

                {{-- Pay to (shown to the buyer while an order is open) --}}
                @if ($isBuyer && in_array($s, ['waiting_payment', 'buyer_paid', 'releasing']))
                    <x-ui.card>
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-banknotes class="h-5 w-5 text-brand-600" />
                            <h3 class="text-base font-semibold text-neutral-900">{{ __('Pay to') }}</h3>
                            @if ($payToAccounts->count() > 1)
                                <span class="ml-auto text-xs font-medium text-neutral-400">{{ __('Pay to any one') }}</span>
                            @endif
                        </div>

                        {{-- Amount to send --}}
                        <div class="mt-3 flex items-center justify-between rounded-xl bg-brand-50 px-4 py-3">
                            <div>
                                <p class="text-xs font-medium text-neutral-500">{{ __('Amount to send') }}</p>
                                <p class="text-xl font-bold tabular text-neutral-900">{{ number_format((float) $order->fiat_amount, 2) }} <span class="text-sm font-semibold text-neutral-500">{{ $order->fiat_currency }}</span></p>
                            </div>
                            <x-ui.copy-text :text="number_format((float) $order->fiat_amount, 2, '.', '')" />
                        </div>

                        @forelse ($payToAccounts as $acc)
                            @php $fields = $acc->method?->fieldSchema() ?? []; @endphp
                            <div class="mt-3 overflow-hidden rounded-xl border border-neutral-200">
                                {{-- Account header: method (account type) + optional label --}}
                                @if ($acc->method?->name || $acc->label)
                                    <div class="flex items-center gap-2 border-b border-neutral-100 bg-neutral-50/70 px-4 py-2.5">
                                        @if ($acc->method?->name)
                                            <span class="grid h-6 w-6 place-items-center rounded-full bg-brand-100 text-[11px] font-bold text-brand-700">{{ mb_strtoupper(mb_substr($acc->method->name, 0, 1)) }}</span>
                                            <span class="text-sm font-semibold text-neutral-800">{{ $acc->method->name }}</span>
                                        @endif
                                        @if ($acc->label)<span class="ml-auto text-xs text-neutral-400">{{ $acc->label }}</span>@endif
                                    </div>
                                @endif
                                {{-- Account fields --}}
                                <div class="divide-y divide-neutral-100 px-4">
                                    @foreach ($fields as $f)
                                        @if (! empty($acc->account[$f['key']]))
                                            <div class="flex items-center justify-between gap-3 py-2.5">
                                                <div class="min-w-0">
                                                    <p class="text-xs text-neutral-500">{{ $f['label'] }}</p>
                                                    <p class="truncate text-sm font-semibold text-neutral-900">{{ $acc->account[$f['key']] }}</p>
                                                </div>
                                                <x-ui.copy-text :text="$acc->account[$f['key']]" />
                                            </div>
                                        @endif
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <p class="mt-3 rounded-lg bg-neutral-50 px-3 py-2.5 text-sm text-neutral-500">
                                {{ __("The seller hasn't saved their :method details. Ask for them in chat before sending money.", ['method' => $order->paymentMethod?->name ?? __('payment')]) }}
                            </p>
                        @endforelse

                        <p class="mt-3 flex items-start gap-1.5 text-xs text-neutral-400">
                            <x-heroicon-s-shield-check class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            {{ __("Send the exact amount, then tap \"I've paid\". Keep records in chat — never pay outside the agreed method.") }}
                        </p>
                    </x-ui.card>
                @endif

                {{-- Seller: nudge to add payout details so buyers can pay --}}
                @if (! $isBuyer && $order->status->isOpen() && $payToAccounts->isEmpty())
                    <x-ui.alert type="warning" :title="__('Add your payout details')">
                        {{ __("Buyers can't see where to pay you.") }}
                        <a href="{{ route('p2p.payment-methods') }}" class="font-semibold underline">{{ __('Add your :method account', ['method' => $order->paymentMethod?->name ?? __('payment')]) }}</a>
                        {{ __('so they can pay without asking.') }}
                    </x-ui.alert>
                @endif

                {{-- Contextual actions --}}
                @php $hasAction = ($s === 'waiting_payment') || (in_array($s, ['buyer_paid', 'releasing'])); @endphp
                @if ($hasAction)
                    <x-ui.card>
                        <div class="space-y-3" x-data>
                            {{-- Primary action --}}
                            @if ($s === 'waiting_payment' && $isBuyer)
                                <form method="POST" action="{{ route('p2p.order.paid', $order) }}">@csrf
                                    <x-ui.button type="submit" variant="success" icon="check" class="w-full">{{ __("I've paid — notify the seller") }}</x-ui.button>
                                </form>
                            @elseif (in_array($s, ['buyer_paid', 'releasing']) && ! $isBuyer)
                                <x-ui.button type="button" variant="success" icon="lock-open" class="w-full" x-on:click="$dispatch('open-modal', 'p2p-release')">{{ __('Release USDT') }}</x-ui.button>
                            @endif

                            {{-- Secondary actions --}}
                            <div class="flex flex-wrap gap-2">
                                @if ($s === 'waiting_payment')
                                    <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('open-modal', 'p2p-cancel')">{{ __('Cancel order') }}</x-ui.button>
                                @endif
                                @if (in_array($s, ['buyer_paid', 'releasing']))
                                    <x-ui.button type="button" variant="danger" icon="exclamation-triangle" x-on:click="$dispatch('open-modal', 'p2p-dispute')">{{ __('Open dispute') }}</x-ui.button>
                                @endif
                            </div>
                        </div>
                    </x-ui.card>
                @endif
            </div>

            {{-- ─── Live chat ─── --}}
            <x-ui.card class="p-0 lg:sticky lg:top-4 lg:self-start">
                <div x-data="p2pChat('{{ $order->id }}', '{{ $me }}')" class="flex h-[34rem] flex-col">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-3.5">
                        <a href="{{ route('p2p.merchant', $cp->getKey()) }}" class="flex items-center gap-2.5 hover:opacity-80">
                            <x-ui.avatar :name="$cp->name" size="sm" />
                            <div class="leading-tight">
                                <p class="flex items-center gap-1 text-sm font-semibold text-neutral-900">
                                    {{ $cp->name }}
                                    @if ($cpVerified)<x-heroicon-s-check-badge class="h-4 w-4 text-brand-500" />@endif
                                </p>
                                <p class="h-3.5 text-xs text-brand-600" x-show="typing" x-cloak>{{ __('typing…') }}</p>
                            </div>
                        </a>
                        <span class="inline-flex items-center gap-1.5 text-xs text-neutral-400"><x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> {{ __('Encrypted') }}</span>
                    </div>

                    <div class="flex-1 space-y-3 overflow-y-auto bg-neutral-50/50 px-5 py-4" x-ref="thread">
                        <template x-for="m in messages" :key="m.id">
                            <div>
                                <template x-if="m.sender_type === 'system'">
                                    <p class="mx-auto max-w-[90%] rounded-full bg-neutral-100 px-3 py-1.5 text-center text-xs text-neutral-500" x-text="m.body"></p>
                                </template>
                                <template x-if="m.sender_type !== 'system'">
                                    <div class="flex" :class="m.sender_id === '{{ $me }}' ? 'justify-end' : 'justify-start'">
                                        <div class="max-w-[80%] rounded-2xl px-3.5 py-2 text-sm shadow-sm"
                                             :class="m.sender_id === '{{ $me }}' ? 'bg-brand-500 text-white' : 'bg-white text-neutral-800 border border-neutral-200'">
                                            <p x-show="m.body" x-text="m.body" class="whitespace-pre-wrap break-words"></p>
                                            <a x-show="m.has_attachment" :href="'/p2p/messages/' + m.id + '/attachment'" target="_blank"
                                               class="mt-1 inline-flex items-center gap-1 text-xs underline">
                                                View attachment
                                            </a>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                        <p x-show="messages.length === 0" class="py-8 text-center text-sm text-neutral-400">{{ __('No messages yet. Say hello') }} 👋</p>
                    </div>

                    <div class="border-t border-neutral-100">
                        <p x-show="error" x-cloak x-text="error" class="px-4 pt-2 text-xs text-red-600"></p>
                        <form x-on:submit.prevent="send()" class="flex items-center gap-2 px-3 py-3">
                            <input type="text" x-model="draft" placeholder="{{ __('Type a message…') }}" autocomplete="off"
                                   x-on:input="whisperTyping()"
                                   x-on:keydown.enter.prevent="send()"
                                   class="pp-input flex-1">
                            <label class="grid h-10 w-10 shrink-0 cursor-pointer place-items-center rounded-lg border border-neutral-200 text-neutral-500 hover:bg-neutral-50" title="{{ __('Attach receipt') }}">
                                <x-heroicon-o-paper-clip class="h-5 w-5" />
                                <input type="file" class="sr-only" accept=".jpg,.jpeg,.png,.webp,.pdf" x-on:change="sendFile($event)">
                            </label>
                            <x-ui.button type="submit" icon="paper-airplane" class="shrink-0" ::disabled="sending">{{ __('Send') }}</x-ui.button>
                        </form>
                    </div>
                </div>
            </x-ui.card>
        </div>

        {{-- Dispute case panel --}}
        @if ($order->dispute)
            <x-ui.card>
                <div class="flex items-center gap-2">
                    <x-heroicon-o-scale class="h-5 w-5 text-red-500" />
                    <h3 class="text-base font-semibold text-neutral-900">{{ __('Dispute') }}</h3>
                    <x-ui.badge :color="$order->dispute->status->color()" dot>{{ $order->dispute->status->label() }}</x-ui.badge>
                </div>
                <p class="mt-3 text-sm font-semibold text-neutral-900">{{ $order->dispute->reason }}</p>
                @if ($order->dispute->detail)<p class="mt-1 text-sm text-neutral-500">{{ $order->dispute->detail }}</p>@endif

                @if ($order->dispute->evidence->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">{{ __('Evidence') }}</p>
                        @foreach ($order->dispute->evidence as $ev)
                            <div class="flex items-center justify-between rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                <div class="min-w-0 truncate">
                                    <span class="font-medium text-neutral-700">{{ ucfirst($ev->uploader_role) }}</span>
                                    @if ($ev->note)<span class="text-neutral-500"> — {{ $ev->note }}</span>@endif
                                </div>
                                <a href="{{ route('p2p.dispute.evidence', $ev) }}" target="_blank" class="ml-3 shrink-0 text-brand-600 hover:underline">{{ __('Download') }}</a>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($order->dispute->status->isOpen())
                    <form method="POST" action="{{ route('p2p.dispute.evidence.add', $order) }}" enctype="multipart/form-data" class="mt-4 space-y-3 border-t border-neutral-100 pt-4">
                        @csrf
                        <x-ui.input name="note" placeholder="{{ __('Add a note (optional)…') }}" :error="$errors->first('note')" />
                        <div class="flex items-center gap-2">
                            <input type="file" name="file" required class="pp-input flex-1">
                            <x-ui.button type="submit" icon="paper-clip">{{ __('Add evidence') }}</x-ui.button>
                        </div>
                        @error('file')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    </form>
                @else
                    <p class="mt-4 border-t border-neutral-100 pt-3 text-sm text-neutral-500">{{ __('Resolved') }}{{ $order->dispute->resolution ? ' — '.$order->dispute->resolution : '' }}.</p>
                @endif
            </x-ui.card>
        @endif

        {{-- Release confirmation modal --}}
        @if (in_array($s, ['buyer_paid', 'releasing']) && ! $isBuyer)
            <x-ui.modal name="p2p-release" :title="__('Release the USDT?')" :subtitle="__('This is final and cannot be undone.')" maxWidth="sm">
                <div class="space-y-4">
                    <div class="flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <x-heroicon-s-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0" />
                        <p>{{ __('Only release once you have confirmed the :amount payment landed in your account. Once released, the USDT goes to the buyer immediately.', ['amount' => number_format((float) $order->fiat_amount, 2).' '.$order->fiat_currency]) }}</p>
                    </div>
                    <form method="POST" action="{{ route('p2p.order.release', $order) }}">
                        @csrf
                        <x-ui.button type="submit" variant="success" icon="lock-open" class="w-full">{{ __('Yes, release :amount', ['amount' => $order->cryptoMoney()->format()]) }}</x-ui.button>
                    </form>
                </div>
            </x-ui.modal>
        @endif

        {{-- Cancel confirmation modal --}}
        @if ($s === 'waiting_payment')
            <x-ui.modal name="p2p-cancel" :title="__('Cancel this order?')" :subtitle="__('The escrow is returned to the seller.')" maxWidth="sm">
                <div class="space-y-4">
                    <p class="text-sm text-neutral-600">{{ __("Only cancel if you haven't paid. If you already sent the money, do not cancel — open a dispute instead.") }}</p>
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('p2p.order.cancel', $order) }}" class="flex-1">
                            @csrf
                            <x-ui.button type="submit" variant="danger" class="w-full">{{ __('Cancel order') }}</x-ui.button>
                        </form>
                        <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'p2p-cancel')">{{ __('Keep it') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.modal>
        @endif

        {{-- Dispute modal --}}
        @if (in_array($s, ['buyer_paid', 'releasing']))
            <x-ui.modal name="p2p-dispute" :title="__('Open a dispute')" :subtitle="__('An operator will review the evidence and rule.')" maxWidth="sm">
                <form method="POST" action="{{ route('p2p.order.dispute', $order) }}" class="space-y-4">
                    @csrf
                    <x-ui.input name="reason" :label="__('Reason')" placeholder="{{ __('e.g. Payment not received') }}" :error="$errors->first('reason')" />
                    <x-ui.textarea name="detail" :label="__('Details (optional)')" rows="3" placeholder="{{ __('Explain what happened…') }}" />
                    <x-ui.button type="submit" variant="danger" class="w-full">{{ __('Submit dispute') }}</x-ui.button>
                </form>
            </x-ui.modal>
        @endif
    </div>
</x-layouts.app>
