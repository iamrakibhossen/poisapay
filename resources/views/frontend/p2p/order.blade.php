<x-layouts.app :title="'Order '.$order->ref">
    @php
        $s = $order->status->value;
        $done = in_array($s, ['completed', 'force_released']);
        $failed = in_array($s, ['cancelled', 'expired', 'force_cancelled']);
        $disputed = $s === 'disputed';

        // Happy-path progress tracker (4 nodes). $current = index of the in-progress
        // node, or 4 when everything is done.
        $steps = [
            ['label' => 'Order created', 'desc' => 'Escrow locked'],
            ['label' => $isBuyer ? 'Payment sent' : 'Payment received', 'desc' => 'Fiat transferred'],
            ['label' => 'Release', 'desc' => 'Seller confirms'],
            ['label' => 'Completed', 'desc' => 'Crypto delivered'],
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
        $sideAccent = $isBuyer ? 'green' : 'red';
    @endphp

    <div class="space-y-6">
        <x-ui.page-header :title="'Order '.$order->ref" subtitle="Crypto is escrowed on-ledger; settle the fiat leg with your counterparty and confirm.">
            <x-slot:actions>
                <a href="{{ route('p2p.orders') }}"><x-ui.button variant="secondary" icon="arrow-left">My orders</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif
        @if (session('error'))<x-ui.alert type="error">{{ session('error') }}</x-ui.alert>@endif

        {{-- ─── Progress tracker + status hero ─── --}}
        <x-ui.card>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="rounded-lg px-2.5 py-1 text-xs font-bold uppercase tracking-wide {{ $isBuyer ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">{{ $isBuyer ? 'Buy' : 'Sell' }}</span>
                    <x-ui.badge :color="$order->status->color()" dot>{{ $order->status->label() }}</x-ui.badge>
                </div>
                @if ($s === 'waiting_payment' && $order->expires_at)
                    <div class="flex items-center gap-2 rounded-full bg-amber-50 px-3 py-1.5 text-sm text-amber-800" x-data="{ left: '' }"
                         x-init="const end = {{ $order->expires_at->timestamp }} * 1000;
                             const t = () => { let d = Math.max(0, Math.floor((end - Date.now())/1000));
                                 left = String(Math.floor(d/60)).padStart(2,'0') + ':' + String(d%60).padStart(2,'0'); };
                             t(); setInterval(t, 1000);">
                        <x-heroicon-o-clock class="h-4 w-4" />
                        Pay within <span class="font-bold tabular" x-text="left"></span>
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
                                <p class="text-xs font-medium text-neutral-500">{{ $isBuyer ? 'You buy' : 'You sell' }}</p>
                                <p class="mt-1 text-2xl font-bold tabular text-neutral-900">{{ $order->cryptoMoney()->format() }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-medium text-neutral-500">{{ $isBuyer ? 'You pay' : 'You receive' }}</p>
                                <p class="mt-1 text-2xl font-bold tabular text-neutral-900">{{ number_format((float) $order->fiat_amount, 2) }} <span class="text-sm font-medium text-neutral-400">{{ $order->fiat_currency }}</span></p>
                            </div>
                        </div>
                    </div>

                    <dl class="mt-4 divide-y divide-neutral-100 text-sm">
                        <div class="flex justify-between py-2.5">
                            <dt class="text-neutral-500">Price</dt>
                            <dd class="text-neutral-700 tabular">{{ number_format((float) $order->price, 4) }} {{ $order->fiat_currency }}</dd>
                        </div>
                        @if ($isBuyer)
                            <div class="flex justify-between py-2.5">
                                <dt class="text-neutral-500">Fee / you receive</dt>
                                <dd class="text-neutral-700 tabular">{{ $order->feeMoney()->format() }} · <span class="font-semibold text-neutral-900">{{ $order->netMoney()->format() }}</span></dd>
                            </div>
                        @endif
                        <div class="flex items-center justify-between py-2.5">
                            <dt class="text-neutral-500">Counterparty</dt>
                            <dd class="flex items-center gap-2">
                                <x-ui.avatar :name="$isBuyer ? $order->seller->name : $order->buyer->name" size="sm" />
                                <span class="font-medium text-neutral-900">{{ $isBuyer ? $order->seller->name : $order->buyer->name }}</span>
                            </dd>
                        </div>
                        @if ($order->paymentMethod)
                            <div class="flex justify-between py-2.5">
                                <dt class="text-neutral-500">Payment method</dt>
                                <dd><span class="inline-flex items-center rounded-md border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-600">{{ $order->paymentMethod->name }}</span></dd>
                            </div>
                        @endif
                    </dl>
                </x-ui.card>

                {{-- What to do now --}}
                @if ($disputed)
                    <x-ui.alert type="warning" title="Under dispute">An operator is reviewing the evidence. Add anything that supports your case in the dispute panel below.</x-ui.alert>
                @elseif ($order->status->isOpen())
                    <div class="rounded-2xl border border-brand-100 bg-brand-50/70 p-4">
                        <div class="flex items-start gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-brand-500 text-white">
                                <x-heroicon-o-information-circle class="h-5 w-5" />
                            </span>
                            <div class="text-sm">
                                <p class="font-semibold text-neutral-900">
                                    @if ($s === 'waiting_payment' && $isBuyer) Send the payment
                                    @elseif ($s === 'waiting_payment') Waiting for the buyer
                                    @elseif (in_array($s, ['buyer_paid', 'releasing']) && ! $isBuyer) Confirm you received the fiat
                                    @else Waiting for the seller to release
                                    @endif
                                </p>
                                <p class="mt-0.5 text-neutral-600">
                                    @if ($s === 'waiting_payment' && $isBuyer)
                                        Pay the seller off-platform with the agreed method, then tap “I’ve paid”. Ask for their account details in chat.
                                    @elseif ($s === 'waiting_payment')
                                        The buyer is sending the fiat. Release the USDT only once you’ve confirmed it arrived in your account.
                                    @elseif (in_array($s, ['buyer_paid', 'releasing']) && ! $isBuyer)
                                        The buyer marked this paid. Verify the money landed, then release the escrow. Open a dispute if it never arrives.
                                    @else
                                        You’ve marked this paid. The seller is verifying and will release your USDT shortly.
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>
                @elseif ($done)
                    <x-ui.alert type="success" title="Trade complete">The escrow was released — {{ $isBuyer ? 'the USDT is in your wallet' : 'the buyer received the USDT' }}.</x-ui.alert>
                @else
                    <x-ui.alert type="info">This order is {{ $order->status->label() }} — no further action needed.</x-ui.alert>
                @endif

                {{-- Pay to (shown to the buyer while an order is open) --}}
                @if ($isBuyer && in_array($s, ['waiting_payment', 'buyer_paid', 'releasing']))
                    <x-ui.card>
                        <div class="flex items-center gap-2">
                            <x-heroicon-o-banknotes class="h-5 w-5 text-brand-600" />
                            <h3 class="text-base font-semibold text-neutral-900">Pay to</h3>
                            @if ($order->paymentMethod)
                                <span class="ml-auto inline-flex items-center rounded-md border border-neutral-200 bg-neutral-50 px-2 py-0.5 text-xs font-medium text-neutral-600">{{ $order->paymentMethod->name }}</span>
                            @endif
                        </div>

                        {{-- Amount to send --}}
                        <div class="mt-3 flex items-center justify-between rounded-xl bg-brand-50 px-4 py-3">
                            <div>
                                <p class="text-xs font-medium text-neutral-500">Amount to send</p>
                                <p class="text-lg font-bold tabular text-neutral-900">{{ number_format((float) $order->fiat_amount, 2) }} {{ $order->fiat_currency }}</p>
                            </div>
                            <x-ui.copy-text :text="number_format((float) $order->fiat_amount, 2, '.', '')" />
                        </div>

                        @forelse ($payToAccounts as $acc)
                            @php $fields = $acc->method?->fields ?: []; @endphp
                            <div class="mt-3 rounded-xl border border-neutral-200 p-4">
                                @if ($acc->label)<p class="mb-1 text-xs text-neutral-400">{{ $acc->label }}</p>@endif
                                @foreach ($fields as $f)
                                    @if (! empty($acc->account[$f['key']]))
                                        <div class="flex items-center justify-between gap-3 border-neutral-100 py-1.5 {{ ! $loop->first ? 'border-t' : '' }}">
                                            <div class="min-w-0">
                                                <p class="text-xs text-neutral-500">{{ $f['label'] }}</p>
                                                <p class="truncate text-sm font-semibold text-neutral-900">{{ $acc->account[$f['key']] }}</p>
                                            </div>
                                            <x-ui.copy-text :text="$acc->account[$f['key']]" />
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @empty
                            <p class="mt-3 rounded-lg bg-neutral-50 px-3 py-2.5 text-sm text-neutral-500">
                                The seller hasn’t saved their {{ $order->paymentMethod?->name ?? 'payment' }} details. Ask for them in chat before sending money.
                            </p>
                        @endforelse

                        <p class="mt-3 flex items-start gap-1.5 text-xs text-neutral-400">
                            <x-heroicon-s-shield-check class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                            Send the exact amount, then tap “I’ve paid”. Keep records in chat — never pay outside the agreed method.
                        </p>
                    </x-ui.card>
                @endif

                {{-- Seller: nudge to add payout details so buyers can pay --}}
                @if (! $isBuyer && $order->status->isOpen() && $payToAccounts->isEmpty())
                    <x-ui.alert type="warning" title="Add your payout details">
                        Buyers can’t see where to pay you.
                        <a href="{{ route('p2p.payment-methods') }}" class="font-semibold underline">Add your {{ $order->paymentMethod?->name ?? 'payment' }} account</a>
                        so they can pay without asking.
                    </x-ui.alert>
                @endif

                {{-- Contextual actions --}}
                @php $hasAction = ($s === 'waiting_payment') || (in_array($s, ['buyer_paid', 'releasing'])); @endphp
                @if ($hasAction)
                    <x-ui.card>
                        <div class="flex flex-wrap gap-2" x-data>
                            @if ($s === 'waiting_payment' && $isBuyer)
                                <form method="POST" action="{{ route('p2p.order.paid', $order) }}">@csrf<x-ui.button type="submit" variant="success" icon="check">I’ve paid</x-ui.button></form>
                            @endif
                            @if (in_array($s, ['buyer_paid', 'releasing']) && ! $isBuyer)
                                <form method="POST" action="{{ route('p2p.order.release', $order) }}">@csrf<x-ui.button type="submit" variant="success" icon="lock-open">Release USDT</x-ui.button></form>
                            @endif
                            @if ($s === 'waiting_payment')
                                <form method="POST" action="{{ route('p2p.order.cancel', $order) }}">@csrf<x-ui.button type="submit" variant="secondary">Cancel order</x-ui.button></form>
                            @endif
                            @if (in_array($s, ['buyer_paid', 'releasing']))
                                <x-ui.button type="button" variant="danger" icon="exclamation-triangle" x-on:click="$dispatch('open-modal', 'p2p-dispute')">Open dispute</x-ui.button>
                            @endif
                        </div>
                    </x-ui.card>
                @endif
            </div>

            {{-- ─── Live chat ─── --}}
            <x-ui.card class="p-0">
                <div x-data="p2pChat('{{ $order->id }}', '{{ $me }}')" class="flex h-[34rem] flex-col">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-5 py-3.5">
                        <div class="flex items-center gap-2.5">
                            <x-ui.avatar :name="$isBuyer ? $order->seller->name : $order->buyer->name" size="sm" />
                            <div class="leading-tight">
                                <p class="text-sm font-semibold text-neutral-900">{{ $isBuyer ? $order->seller->name : $order->buyer->name }}</p>
                                <p class="h-3.5 text-xs text-brand-600" x-show="typing" x-cloak>typing…</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 text-xs text-neutral-400"><x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> Encrypted</span>
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
                        <p x-show="messages.length === 0" class="py-8 text-center text-sm text-neutral-400">No messages yet. Say hello 👋</p>
                    </div>

                    <form method="POST" action="{{ route('p2p.messages.send', $order) }}" enctype="multipart/form-data"
                          class="flex items-center gap-2 border-t border-neutral-100 px-3 py-3">
                        @csrf
                        <input type="hidden" name="type" value="text">
                        <input type="text" name="body" placeholder="Type a message…" autocomplete="off"
                               x-on:input="whisperTyping()"
                               class="pp-input flex-1">
                        <label class="grid h-10 w-10 shrink-0 cursor-pointer place-items-center rounded-lg border border-neutral-200 text-neutral-500 hover:bg-neutral-50" title="Attach receipt">
                            <x-heroicon-o-paper-clip class="h-5 w-5" />
                            <input type="file" name="attachment" class="sr-only" onchange="this.form.querySelector('[name=type]').value='receipt'; this.form.submit();">
                        </label>
                        <x-ui.button type="submit" icon="paper-airplane" class="shrink-0">Send</x-ui.button>
                    </form>
                </div>
            </x-ui.card>
        </div>

        {{-- Dispute case panel --}}
        @if ($order->dispute)
            <x-ui.card>
                <div class="flex items-center gap-2">
                    <x-heroicon-o-scale class="h-5 w-5 text-red-500" />
                    <h3 class="text-base font-semibold text-neutral-900">Dispute</h3>
                    <x-ui.badge :color="$order->dispute->status->color()" dot>{{ $order->dispute->status->label() }}</x-ui.badge>
                </div>
                <p class="mt-3 text-sm font-semibold text-neutral-900">{{ $order->dispute->reason }}</p>
                @if ($order->dispute->detail)<p class="mt-1 text-sm text-neutral-500">{{ $order->dispute->detail }}</p>@endif

                @if ($order->dispute->evidence->isNotEmpty())
                    <div class="mt-4 space-y-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-neutral-400">Evidence</p>
                        @foreach ($order->dispute->evidence as $ev)
                            <div class="flex items-center justify-between rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                <div class="min-w-0 truncate">
                                    <span class="font-medium text-neutral-700">{{ ucfirst($ev->uploader_role) }}</span>
                                    @if ($ev->note)<span class="text-neutral-500"> — {{ $ev->note }}</span>@endif
                                </div>
                                <a href="{{ route('p2p.dispute.evidence', $ev) }}" target="_blank" class="ml-3 shrink-0 text-brand-600 hover:underline">Download</a>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($order->dispute->status->isOpen())
                    <form method="POST" action="{{ route('p2p.dispute.evidence.add', $order) }}" enctype="multipart/form-data" class="mt-4 space-y-3 border-t border-neutral-100 pt-4">
                        @csrf
                        <x-ui.input name="note" placeholder="Add a note (optional)…" :error="$errors->first('note')" />
                        <div class="flex items-center gap-2">
                            <input type="file" name="file" required class="pp-input flex-1">
                            <x-ui.button type="submit" icon="paper-clip">Add evidence</x-ui.button>
                        </div>
                        @error('file')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    </form>
                @else
                    <p class="mt-4 border-t border-neutral-100 pt-3 text-sm text-neutral-500">Resolved{{ $order->dispute->resolution ? ' — '.$order->dispute->resolution : '' }}.</p>
                @endif
            </x-ui.card>
        @endif

        {{-- Dispute modal --}}
        @if (in_array($s, ['buyer_paid', 'releasing']))
            <x-ui.modal name="p2p-dispute" title="Open a dispute" subtitle="An operator will review the evidence and rule." maxWidth="sm">
                <form method="POST" action="{{ route('p2p.order.dispute', $order) }}" class="space-y-4">
                    @csrf
                    <x-ui.input name="reason" label="Reason" placeholder="e.g. Payment not received" :error="$errors->first('reason')" />
                    <x-ui.textarea name="detail" label="Details (optional)" rows="3" placeholder="Explain what happened…" />
                    <x-ui.button type="submit" variant="danger" class="w-full">Submit dispute</x-ui.button>
                </form>
            </x-ui.modal>
        @endif
    </div>
</x-layouts.app>
