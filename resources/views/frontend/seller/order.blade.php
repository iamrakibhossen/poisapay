<x-layouts.app :title="__('Order')">
    <div class="mx-auto mt-6 max-w-4xl space-y-5">
        {{-- Header --}}
        <div>
            <a href="{{ route('shop.orders') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900">
                <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Orders') }}
            </a>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="font-mono text-xl font-semibold tracking-tight text-neutral-900">{{ $order['number'] }}</h1>
                    <x-ui.badge :color="$order['statusColor']" dot>{{ $order['status'] }}</x-ui.badge>
                </div>
                <p class="text-xs text-neutral-400">{{ $order['placedAt'] }}</p>
            </div>
        </div>

        @if (session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif

        <div class="grid gap-5 lg:grid-cols-3">
            {{-- Left: items + fulfilment + activity --}}
            <div class="space-y-5 lg:col-span-2">
                {{-- Items --}}
                <x-ui.card>
                    <p class="mb-3 text-sm font-semibold text-neutral-900">{{ __('Items') }}</p>
                    @foreach ($order['items'] as $it)
                        <div class="flex items-center gap-3 rounded-xl border border-neutral-200 p-3">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-o-cube class="h-5 w-5" /></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-medium text-neutral-900">{{ $it['name'] }}</p>
                                <p class="text-xs text-neutral-500">{{ $it['variant'] ? $it['variant'].' · ' : '' }}{{ __('Qty') }} {{ $it['qty'] }}</p>
                            </div>
                            <span class="text-sm font-semibold text-neutral-900">{{ $it['price'] }}</span>
                        </div>
                    @endforeach
                </x-ui.card>

                {{-- Fulfilment — advance the order along its lifecycle --}}
                @if (count($order['nextSteps']))
                    <x-ui.card>
                        <p class="mb-3 text-sm font-semibold text-neutral-900">{{ __('Fulfilment') }}</p>
                        <form method="POST" action="{{ route('shop.order.status', ['id' => $order['id']]) }}"
                            x-data="{ status: @js($order['nextSteps'][0]['value']) }" class="space-y-3">
                            @csrf
                            <div class="grid gap-3 sm:grid-cols-2">
                                <x-ui.select :label="__('Advance to')" name="status" x-model="status">
                                    @foreach ($order['nextSteps'] as $step)
                                        <option value="{{ $step['value'] }}">{{ $step['label'] }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                            @error('status')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror

                            {{-- Carrier + tracking only when shipping --}}
                            @if ($order['physical'])
                                <div x-show="status === 'shipped'" x-cloak class="grid gap-3 sm:grid-cols-2">
                                    <x-ui.select :label="__('Carrier')" name="carrier">
                                        @foreach ($order['carriers'] as $c)
                                            <option @selected(($order['shipping']['carrier'] ?? null) === $c)>{{ $c }}</option>
                                        @endforeach
                                    </x-ui.select>
                                    <x-ui.input :label="__('Tracking number')" name="tracking" :value="$order['shipping']['tracking'] ?? ''" placeholder="BD-7712-9920" />
                                </div>
                            @endif

                            <x-ui.button type="submit" icon="check">{{ __('Update order') }}</x-ui.button>
                        </form>
                    </x-ui.card>
                @endif

                {{-- Buyer refund request awaiting the seller --}}
                @if ($order['openRefund'])
                    @php $rr = $order['openRefund']; @endphp
                    <x-ui.card>
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-sm font-semibold text-neutral-900">{{ __('Refund request') }}</p>
                            <x-ui.badge :color="$rr['statusColor']" dot>{{ $rr['status'] }}</x-ui.badge>
                        </div>
                        <p class="text-sm text-neutral-600">{{ __('The buyer requested a :type refund of', ['type' => $rr['type']]) }} <span class="font-semibold text-neutral-900">{{ $rr['amount'] }}</span>.</p>
                        @if ($rr['reason'])<p class="mt-2 rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-500">“{{ $rr['reason'] }}”</p>@endif
                        @if ($rr['escalated'])<p class="mt-2 text-xs font-medium text-blue-600">{{ __('Escalated to support — an operator may also resolve this.') }}</p>@endif
                        @error('refund')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror

                        <form method="POST" action="{{ route('shop.order.refund-request.approve', ['id' => $order['id'], 'refundRequest' => $rr['id']]) }}" class="mt-3 space-y-2">
                            @csrf
                            <textarea name="note" rows="2" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" placeholder="{{ __('Optional note to the buyer') }}"></textarea>
                            <div class="flex gap-2">
                                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-brand-500 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-brand-600"><x-heroicon-o-check class="h-4 w-4" /> {{ __('Approve & refund') }}</button>
                                <button type="submit" formaction="{{ route('shop.order.refund-request.reject', ['id' => $order['id'], 'refundRequest' => $rr['id']]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3.5 py-2 text-sm font-semibold text-neutral-600 transition hover:bg-neutral-50"><x-heroicon-o-x-mark class="h-4 w-4" /> {{ __('Decline') }}</button>
                            </div>
                        </form>
                    </x-ui.card>
                @endif

                {{-- Refund — reverse the payment to the buyer --}}
                @if ($order['refundedAt'])
                    <x-ui.card>
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-500"><x-heroicon-o-arrow-uturn-left class="h-4 w-4" /></span>
                            <div>
                                <p class="text-sm font-semibold text-neutral-900">{{ __('Refunded') }}</p>
                                <p class="text-xs text-neutral-500">{{ __('The buyer was repaid on :date.', ['date' => $order['refundedAt']]) }}</p>
                            </div>
                        </div>
                    </x-ui.card>
                @elseif ($order['refundable'])
                    <x-ui.card>
                        <p class="mb-1 text-sm font-semibold text-neutral-900">{{ __('Refund') }}</p>
                        <p class="mb-3 text-xs text-neutral-500">{{ __('Return :amount to the buyer and reverse your earnings. This can’t be undone.', ['amount' => $order['refundTotal']]) }}</p>
                        @error('refund')<p class="mb-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                        <button type="button" x-on:click="$dispatch('open-modal', 'refund-order')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 px-3.5 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-50">
                            <x-heroicon-o-arrow-uturn-left class="h-4 w-4" /> {{ __('Refund order') }}
                        </button>
                    </x-ui.card>

                    <x-ui.modal name="refund-order" :title="__('Refund this order?')"
                        :subtitle="__('The buyer will be repaid :amount to their PoisaPay wallet.', ['amount' => $order['refundTotal']])">
                        <form method="POST" action="{{ route('shop.order.refund', ['id' => $order['id']]) }}" class="space-y-4">
                            @csrf
                            <x-ui.textarea name="reason" :label="__('Reason (optional)')" rows="3"
                                :placeholder="__('Shared on the order timeline — e.g. buyer request, item unavailable.')" />
                            <div class="flex justify-end gap-2">
                                <button type="button" x-on:click="$dispatch('close-modal', 'refund-order')"
                                    class="rounded-lg border border-neutral-200 px-4 py-2 text-sm font-semibold text-neutral-600 transition hover:bg-neutral-50">{{ __('Cancel') }}</button>
                                <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-rose-700">
                                    <x-heroicon-o-arrow-uturn-left class="h-4 w-4" /> {{ __('Refund :amount', ['amount' => $order['refundTotal']]) }}
                                </button>
                            </div>
                        </form>
                    </x-ui.modal>
                @endif

                {{-- Conversation — order-scoped, shared with the buyer --}}
                <x-ui.card>
                    <div class="mb-3 flex items-center justify-between">
                        <p class="text-sm font-semibold text-neutral-900">{{ __('Messages') }}</p>
                        <span class="inline-flex items-center gap-1 text-[11px] text-neutral-400"><x-heroicon-o-users class="h-3.5 w-3.5" /> {{ __('Buyer & seller') }}</span>
                    </div>

                    <div class="max-h-72 space-y-3 overflow-y-auto rounded-xl bg-neutral-50/50 p-3">
                        @forelse ($order['messages'] as $m)
                            <div class="flex {{ $m['side'] === 'mine' ? 'justify-end' : 'justify-start' }}">
                                <div class="max-w-[78%]">
                                    <p class="mb-0.5 text-[11px] text-neutral-400 {{ $m['side'] === 'mine' ? 'text-right' : '' }}">{{ $m['author'] }}</p>
                                    <div class="rounded-2xl px-3.5 py-2 text-sm {{ $m['side'] === 'mine' ? 'rounded-br-sm bg-brand-500 text-white' : 'rounded-bl-sm border border-neutral-200 bg-white text-neutral-800' }}">{{ $m['body'] }}</div>
                                    <p class="mt-0.5 text-[11px] text-neutral-300 {{ $m['side'] === 'mine' ? 'text-right' : '' }}">{{ $m['at'] }}</p>
                                </div>
                            </div>
                        @empty
                            <p class="py-6 text-center text-xs text-neutral-400">{{ __('No messages yet. Reply to the buyer here.') }}</p>
                        @endforelse
                    </div>

                    <form method="POST" action="{{ route('shop.order.message', ['id' => $order['id']]) }}" class="mt-3 flex items-end gap-2">
                        @csrf
                        <textarea name="body" rows="1" required placeholder="{{ __('Reply to the buyer…') }}"
                            class="max-h-28 min-h-[42px] flex-1 resize-none rounded-xl border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">{{ old('body') }}</textarea>
                        <x-ui.button type="submit" icon="paper-airplane">{{ __('Send') }}</x-ui.button>
                    </form>
                    @error('body')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-ui.card>

                {{-- Activity — real order events --}}
                @if (count($order['events']))
                    <x-ui.card>
                        <p class="mb-4 text-sm font-semibold text-neutral-900">{{ __('Activity') }}</p>
                        <div class="space-y-4">
                            @foreach ($order['events'] as $e)
                                <div class="flex items-center gap-3">
                                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500 text-white"><x-heroicon-s-check class="h-3.5 w-3.5" /></span>
                                    <p class="flex-1 text-sm font-medium text-neutral-900">{{ $e['label'] }}</p>
                                    <span class="text-xs text-neutral-400">{{ $e['at'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif
            </div>

            {{-- Right: customer + shipping + totals --}}
            <div class="space-y-5">
                <x-ui.card>
                    <p class="mb-2 text-sm font-semibold text-neutral-900">{{ __('Customer') }}</p>
                    <p class="text-sm text-neutral-800">{{ $order['buyer']['name'] }}</p>
                    <p class="text-xs text-neutral-500">{{ $order['buyer']['email'] }}</p>
                </x-ui.card>

                @if ($order['physical'] && ($order['shipping']['line1'] ?? null))
                    <x-ui.card>
                        <p class="mb-2 text-sm font-semibold text-neutral-900">{{ __('Shipping address') }}</p>
                        <div class="text-sm leading-relaxed text-neutral-700">
                            <p>{{ $order['shipping']['name'] }}</p>
                            <p>{{ $order['shipping']['line1'] }}</p>
                            <p>{{ $order['shipping']['city'] }} {{ $order['shipping']['postcode'] }}</p>
                            <p>{{ $order['shipping']['country'] }}</p>
                            @if ($order['shipping']['phone'] ?? null)<p class="mt-1 text-neutral-500">{{ $order['shipping']['phone'] }}</p>@endif
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
            </div>
        </div>
    </div>
</x-layouts.app>
