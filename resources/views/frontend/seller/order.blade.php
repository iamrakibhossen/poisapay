<x-layouts.app :title="__('Order')">
    <div class="mx-auto mt-6 max-w-4xl space-y-5"
        x-data="{
            shipped: false,
            messages: @js($order['messages']),
            reply: '',
            send() {
                const t = this.reply.trim();
                if (! t) return;
                this.messages.push({ from: 'seller', author: 'You', body: t, at: 'Just now' });
                this.reply = '';
                this.$nextTick(() => { const el = this.$refs.thread; if (el) el.scrollTop = el.scrollHeight; });
            },
        }">
        {{-- Header --}}
        <div>
            <a href="{{ route('seller.orders') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900">
                <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Orders') }}
            </a>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="font-mono text-xl font-semibold tracking-tight text-neutral-900">{{ $order['id'] }}</h1>
                    <x-ui.badge :color="$order['statusColor']" dot x-show="! shipped">{{ $order['status'] }}</x-ui.badge>
                    <x-ui.badge color="info" dot x-show="shipped" x-cloak>{{ __('Shipped') }}</x-ui.badge>
                </div>
                <p class="text-xs text-neutral-400">{{ $order['placedAt'] }}</p>
            </div>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            {{-- Left: items + fulfillment --}}
            <div class="space-y-5 lg:col-span-2">
                {{-- Items --}}
                <x-ui.card>
                    <p class="mb-3 text-sm font-semibold text-neutral-900">{{ __('Items') }}</p>
                    @foreach ($order['items'] as $it)
                        <div class="flex items-center gap-3 rounded-xl border border-neutral-200 p-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-o-cube class="h-5 w-5" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-neutral-900">{{ $it['name'] }}</p>
                                <p class="text-xs text-neutral-500">{{ $it['variant'] }} · {{ __('Qty') }} {{ $it['qty'] }}</p>
                            </div>
                            <span class="text-sm font-semibold text-neutral-900">{{ $it['price'] }}</span>
                        </div>
                    @endforeach
                </x-ui.card>

                {{-- Fulfillment (physical) --}}
                @if ($order['type'] === 'physical')
                    <x-ui.card>
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-sm font-semibold text-neutral-900">{{ __('Fulfillment') }}</p>
                            <x-ui.badge color="warning" dot x-show="! shipped">{{ __('Awaiting shipment') }}</x-ui.badge>
                            <x-ui.badge color="success" dot x-show="shipped" x-cloak>{{ __('Shipped') }}</x-ui.badge>
                        </div>

                        {{-- Before shipping: enter carrier + tracking --}}
                        <div x-show="! shipped" class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-ui.select :label="__('Carrier')" name="carrier">
                                    @foreach ($order['carriers'] as $c)
                                        <option>{{ $c }}</option>
                                    @endforeach
                                </x-ui.select>
                                <x-ui.input :label="__('Tracking number')" name="tracking" placeholder="BD-7712-9920" />
                            </div>
                            <x-ui.button x-on:click="shipped = true" icon="truck">{{ __('Mark as shipped') }}</x-ui.button>
                        </div>

                        {{-- After shipping --}}
                        <div x-show="shipped" x-cloak class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-3">
                            <p class="flex items-center gap-2 text-sm font-medium text-emerald-700"><x-heroicon-o-check-circle class="h-4 w-4" /> {{ __('Marked as shipped — the buyer has been notified with tracking.') }}</p>
                        </div>
                    </x-ui.card>
                @endif

                {{-- Timeline --}}
                <x-ui.card>
                    <p class="mb-4 text-sm font-semibold text-neutral-900">{{ __('Timeline') }}</p>
                    <div class="space-y-4">
                        @foreach ($order['timeline'] as $i => [$label, $at, $done])
                            <div class="flex items-center gap-3">
                                <span @class([
                                    'grid h-6 w-6 shrink-0 place-items-center rounded-full text-[11px]',
                                    'bg-brand-500 text-white' => $done,
                                    'bg-neutral-100 text-neutral-400' => ! $done,
                                ])>
                                    @if ($done)<x-heroicon-s-check class="h-3.5 w-3.5" />@else{{ $i + 1 }}@endif
                                </span>
                                <div class="flex-1">
                                    <p class="text-sm font-medium {{ $done ? 'text-neutral-900' : 'text-neutral-400' }}">{{ $label }}</p>
                                </div>
                                <span class="text-xs text-neutral-400">{{ $at }}</span>
                            </div>
                        @endforeach
                    </div>
                </x-ui.card>

                {{-- Messages — order-scoped conversation shared by buyer & seller --}}
                <x-ui.card>
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-semibold text-neutral-900">{{ __('Messages') }}</p>
                        <span class="inline-flex items-center gap-1 text-[11px] text-neutral-400"><x-heroicon-o-users class="h-3.5 w-3.5" /> {{ __('Buyer & seller') }}</span>
                    </div>

                    <div class="max-h-72 space-y-3 overflow-y-auto rounded-xl bg-neutral-50/50 p-3" x-ref="thread">
                        <template x-for="(m, mi) in messages" :key="mi">
                            <div class="flex" :class="m.from === 'seller' ? 'justify-end' : 'justify-start'">
                                <div class="max-w-[78%]">
                                    <p class="mb-0.5 text-[11px] text-neutral-400" :class="m.from === 'seller' && 'text-right'" x-text="m.author"></p>
                                    <div class="rounded-2xl px-3.5 py-2 text-sm"
                                        :class="m.from === 'seller' ? 'bg-brand-500 text-white rounded-br-sm' : 'border border-neutral-200 bg-white text-neutral-800 rounded-bl-sm'"
                                        x-text="m.body"></div>
                                    <p class="mt-0.5 text-[11px] text-neutral-300" :class="m.from === 'seller' && 'text-right'" x-text="m.at"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    <div class="mt-3 flex items-end gap-2">
                        <textarea x-model="reply" x-on:keydown.enter.prevent="send()" rows="1" placeholder="{{ __('Reply to the buyer…') }}"
                            class="max-h-28 min-h-[42px] flex-1 resize-none rounded-xl border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500"></textarea>
                        <x-ui.button x-on:click="send()" icon="paper-airplane">{{ __('Send') }}</x-ui.button>
                    </div>
                </x-ui.card>
            </div>

            {{-- Right: customer + shipping + totals --}}
            <div class="space-y-5">
                <x-ui.card>
                    <p class="mb-2 text-sm font-semibold text-neutral-900">{{ __('Customer') }}</p>
                    <p class="text-sm text-neutral-800">{{ $order['buyer']['name'] }}</p>
                    <p class="text-xs text-neutral-500">{{ $order['buyer']['email'] }}</p>
                </x-ui.card>

                @if ($order['type'] === 'physical')
                    <x-ui.card>
                        <p class="mb-2 text-sm font-semibold text-neutral-900">{{ __('Shipping address') }}</p>
                        <div class="text-sm leading-relaxed text-neutral-700">
                            <p>{{ $order['shipping']['name'] }}</p>
                            <p>{{ $order['shipping']['line1'] }}</p>
                            <p>{{ $order['shipping']['city'] }} {{ $order['shipping']['postcode'] }}</p>
                            <p>{{ $order['shipping']['country'] }}</p>
                            <p class="mt-1 text-neutral-500">{{ $order['shipping']['phone'] }}</p>
                        </div>
                    </x-ui.card>
                @endif

                <x-ui.card>
                    <p class="mb-3 text-sm font-semibold text-neutral-900">{{ __('Summary') }}</p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between text-neutral-600"><dt>{{ __('Subtotal') }}</dt><dd class="tabular">{{ $order['totals']['subtotal'] }}</dd></div>
                        <div class="flex justify-between text-neutral-600"><dt>{{ __('Shipping') }}</dt><dd class="tabular">{{ $order['totals']['shipping'] }}</dd></div>
                        <div class="flex justify-between border-t border-neutral-100 pt-2 font-semibold text-neutral-900"><dt>{{ __('Total') }}</dt><dd class="tabular">{{ $order['totals']['total'] }}</dd></div>
                        <div class="flex justify-between text-neutral-500"><dt>{{ __('Platform fee') }}</dt><dd class="tabular">{{ $order['totals']['fee'] }}</dd></div>
                        <div class="flex justify-between font-semibold text-emerald-700"><dt>{{ __('Your earnings') }}</dt><dd class="tabular">{{ $order['totals']['net'] }}</dd></div>
                    </dl>
                </x-ui.card>

                <x-ui.button variant="secondary" class="w-full" icon="arrow-uturn-left">{{ __('Refund order') }}</x-ui.button>
            </div>
        </div>
    </div>
</x-layouts.app>
