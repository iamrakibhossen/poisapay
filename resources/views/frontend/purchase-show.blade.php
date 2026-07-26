<x-layouts.app :title="__('Purchase')">
    <div class="mx-auto mt-6 max-w-3xl space-y-5">
        {{-- Header --}}
        <div>
            <a href="{{ route('purchases') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900">
                <x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('My purchases') }}
            </a>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <h1 class="font-mono text-xl font-semibold tracking-tight text-neutral-900">{{ $order['number'] }}</h1>
                    <x-ui.badge :color="$order['statusColor']" dot>{{ $order['status'] }}</x-ui.badge>
                </div>
                <p class="text-xs text-neutral-400">{{ $order['placedAt'] }}</p>
            </div>
            <p class="mt-1 text-sm text-neutral-500">{{ __('Sold by') }} {{ $order['seller'] }}</p>
        </div>

        @if (session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                {{-- Items + delivery --}}
                @foreach ($items as $p)
                    <x-ui.card>
                        <div class="flex items-start gap-3">
                            @if (! empty($p['image']))
                                <img src="{{ $p['image'] }}" alt="" class="h-12 w-12 shrink-0 rounded-lg object-cover ring-1 ring-neutral-200" />
                            @else
                                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-dynamic-component :component="'heroicon-o-'.$p['icon']" class="h-5 w-5" /></span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-neutral-900">{{ $p['name'] }}</p>
                                <p class="text-xs text-neutral-500">{{ $p['price'] }}</p>
                                @if (($p['type'] ?? '') === 'digital')
                                    @if (! empty($p['downloadUrl']))
                                        <p class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><x-heroicon-o-check-circle class="h-4 w-4" /> {{ __('Ready to download') }}</p>
                                    @elseif (! empty($p['fileStatus']))
                                        <p class="mt-1 inline-flex items-center gap-1 text-xs text-amber-600"><x-heroicon-o-clock class="h-4 w-4" /> {{ $p['fileStatus'] }}</p>
                                    @endif
                                @endif
                            </div>
                            @if (! empty($p['downloadUrl']))
                                <x-ui.button href="{{ $p['downloadUrl'] }}" size="sm" icon="arrow-down-tray">{{ __('Download') }}</x-ui.button>
                            @elseif (! empty($p['fileStatus']))
                                <x-ui.button size="sm" icon="clock" variant="secondary" disabled>{{ $p['fileStatus'] }}</x-ui.button>
                            @elseif (! empty($p['productUrl']))
                                <x-ui.button href="{{ $p['productUrl'] }}" size="sm" variant="secondary" icon="arrow-path">{{ __('Buy again') }}</x-ui.button>
                            @endif
                        </div>

                        {{-- License key --}}
                        @if (! empty($p['licenseKey']))
                            <div class="mt-3 flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50/60 px-3 py-2" x-data="{ copied: false }">
                                <code class="flex-1 truncate font-mono text-sm text-neutral-800">{{ $p['licenseKey'] }}</code>
                                <button type="button" x-on:click="navigator.clipboard.writeText('{{ $p['licenseKey'] }}'); copied = true; setTimeout(() => copied = false, 1500)" class="text-xs font-medium text-brand-600 hover:text-brand-700">
                                    <span x-show="! copied">{{ __('Copy') }}</span><span x-show="copied" x-cloak>{{ __('Copied!') }}</span>
                                </button>
                            </div>
                        @endif

                        {{-- File hint --}}
                        @if (! empty($p['file']))
                            <p class="mt-2 inline-flex items-center gap-1 text-xs text-neutral-400"><x-heroicon-o-document class="h-3.5 w-3.5" /> {{ $p['file'] }}</p>
                        @endif

                        {{-- Review --}}
                        @if (! empty($p['canReview']))
                            <div class="mt-3 border-t border-neutral-100 pt-3" x-data="{ open: {{ $errors->has('review') ? 'true' : 'false' }}, rating: {{ $p['review']['rating'] ?? 0 }} }">
                                @if ($p['review'])
                                    <div class="rounded-xl bg-neutral-50/70 p-3">
                                        <div class="flex items-center gap-1 text-amber-500">
                                            @for ($s = 1; $s <= 5; $s++)<span>{{ $s <= $p['review']['rating'] ? '★' : '☆' }}</span>@endfor
                                            <span class="ml-2 text-xs font-medium text-neutral-500">{{ __('Your review') }}</span>
                                        </div>
                                        @if ($p['review']['body'])<p class="mt-1 text-xs text-neutral-600">{{ $p['review']['body'] }}</p>@endif
                                        @if ($p['review']['reply'])
                                            <div class="mt-2 rounded-lg border-l-2 border-brand-300 bg-white px-3 py-2">
                                                <p class="text-[11px] font-semibold text-brand-700">{{ __('Seller replied') }}</p>
                                                <p class="text-xs text-neutral-600">{{ $p['review']['reply'] }}</p>
                                            </div>
                                        @endif
                                        <button type="button" x-on:click="open = ! open" class="mt-2 text-[11px] font-medium text-brand-600 hover:text-brand-700">{{ __('Edit review') }}</button>
                                    </div>
                                @else
                                    <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700"><x-heroicon-o-star class="h-3.5 w-3.5" /> {{ __('Leave a review') }}</button>
                                @endif

                                <form method="POST" action="{{ route('purchases.review', ['order' => $p['orderId']]) }}" x-show="open" x-cloak class="mt-3 space-y-2">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $p['productId'] }}" />
                                    <div class="flex items-center gap-1">
                                        <template x-for="s in [1,2,3,4,5]" :key="s">
                                            <button type="button" x-on:click="rating = s" class="text-xl" :class="s <= rating ? 'text-amber-500' : 'text-neutral-300'">★</button>
                                        </template>
                                        <input type="hidden" name="rating" :value="rating" />
                                    </div>
                                    <input name="title" value="{{ old('title') }}" maxlength="160" placeholder="{{ __('Title (optional)') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                    <textarea name="body" rows="2" maxlength="2000" placeholder="{{ __('Share what you thought…') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">{{ old('body') }}</textarea>
                                    @error('review')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                                    <x-ui.button type="submit" size="sm" icon="paper-airplane" x-bind:disabled="! rating">{{ __('Submit review') }}</x-ui.button>
                                </form>
                            </div>
                        @endif
                    </x-ui.card>
                @endforeach

                {{-- Shipping / tracking (physical) --}}
                @if ($order['physical'] && $order['shipping'])
                    <x-ui.card>
                        <p class="mb-3 text-sm font-semibold text-neutral-900">{{ __('Delivery') }}</p>
                        <div class="grid gap-3 text-sm sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-medium text-neutral-400">{{ __('Ship to') }}</p>
                                <p class="text-neutral-700">{{ $order['shipping']['address'] }}</p>
                            </div>
                            <div>
                                <p class="text-xs font-medium text-neutral-400">{{ __('Carrier / tracking') }}</p>
                                <p class="text-neutral-700">{{ $order['shipping']['carrier'] }} · {{ $order['shipping']['tracking'] }}</p>
                            </div>
                        </div>
                        <div class="mt-4 space-y-3 border-t border-neutral-100 pt-4">
                            @foreach ($order['shipping']['timeline'] as [$label, $at, $done])
                                <div class="flex items-center gap-3">
                                    <span @class([
                                        'grid h-6 w-6 shrink-0 place-items-center rounded-full text-[11px]',
                                        'bg-brand-500 text-white' => $done,
                                        'bg-neutral-100 text-neutral-400' => ! $done,
                                    ])>@if ($done)<x-heroicon-s-check class="h-3.5 w-3.5" />@else{{ $loop->iteration }}@endif</span>
                                    <p class="flex-1 text-sm font-medium {{ $done ? 'text-neutral-900' : 'text-neutral-400' }}">{{ $label }}</p>
                                    <span class="text-xs text-neutral-400">{{ $at }}</span>
                                </div>
                            @endforeach
                        </div>
                    </x-ui.card>
                @endif
            </div>

            {{-- Right: summary + actions --}}
            <div class="space-y-5">
                <x-ui.card>
                    <p class="mb-3 text-sm font-semibold text-neutral-900">{{ __('Summary') }}</p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between text-neutral-600"><dt>{{ __('Subtotal') }}</dt><dd class="tabular">{{ $order['totals']['subtotal'] }}</dd></div>
                        <div class="flex justify-between text-neutral-600"><dt>{{ __('Shipping') }}</dt><dd class="tabular">{{ $order['totals']['shipping'] }}</dd></div>
                        <div class="flex justify-between border-t border-neutral-100 pt-2 font-semibold text-neutral-900"><dt>{{ __('Total') }}</dt><dd class="tabular">{{ $order['totals']['total'] }}</dd></div>
                    </dl>
                </x-ui.card>

                {{-- Refunds --}}
                @php $r = $order['refund']; @endphp
                <x-ui.card>
                    <p class="mb-1 text-sm font-semibold text-neutral-900">{{ __('Refund') }}</p>

                    @if ($r['request'])
                        <div class="mt-2 space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-500">{{ __('Status') }}</span>
                                <x-ui.badge :color="$r['request']['statusColor']" dot>{{ $r['request']['status'] }}</x-ui.badge>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-neutral-500">{{ __('Amount') }}</span>
                                <span class="tabular font-semibold text-neutral-900">{{ $r['request']['amount'] }}</span>
                            </div>
                            @if ($r['request']['note'])
                                <p class="rounded-lg bg-neutral-50 px-3 py-2 text-xs text-neutral-500">“{{ $r['request']['note'] }}”</p>
                            @endif
                        </div>
                        @error('refund')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                        <div class="mt-3 flex gap-2">
                            @if ($r['request']['canCancel'])
                                <form method="POST" action="{{ route('purchases.refund.cancel', ['refundRequest' => $r['request']['id']]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg border border-neutral-200 px-3 py-1.5 text-xs font-semibold text-neutral-600 transition hover:bg-neutral-50">{{ __('Cancel request') }}</button>
                                </form>
                            @endif
                            @if ($r['request']['canEscalate'])
                                <form method="POST" action="{{ route('purchases.refund.escalate', ['refundRequest' => $r['request']['id']]) }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-neutral-900 px-3 py-1.5 text-xs font-semibold text-white transition hover:bg-neutral-800"><x-heroicon-o-flag class="h-3.5 w-3.5" /> {{ __('Escalate to support') }}</button>
                                </form>
                            @endif
                        </div>
                    @elseif ($r['canRequest'])
                        <p class="mb-3 text-xs text-neutral-500">{{ __('Up to :amount refundable.', ['amount' => $r['remaining']]) }}</p>
                        @error('refund')<p class="mb-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                        <button type="button" x-on:click="$dispatch('open-modal', 'refund-request')"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-neutral-200 px-3.5 py-2 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-50">
                            <x-heroicon-o-arrow-uturn-left class="h-4 w-4" /> {{ __('Request a refund') }}
                        </button>
                    @else
                        <p class="mt-1 text-xs text-neutral-400">{{ __('This order is not eligible for a refund.') }}</p>
                    @endif
                </x-ui.card>

                @if (! $r['request'] && $r['canRequest'])
                    <x-ui.modal name="refund-request" :title="__('Request a refund')" :subtitle="__('Up to :amount can be refunded.', ['amount' => $r['remaining']])">
                        <form method="POST" action="{{ route('purchases.refund', ['order' => $order['id']]) }}" class="space-y-4"
                            x-data="{ type: 'full' }">
                            @csrf
                            <div class="grid grid-cols-2 gap-2">
                                <label :class="type === 'full' ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-neutral-200 text-neutral-600'" class="cursor-pointer rounded-lg border px-3 py-2 text-center text-sm font-semibold">
                                    <input type="radio" name="type" value="full" x-model="type" class="sr-only"> {{ __('Full') }}
                                </label>
                                <label :class="type === 'partial' ? 'border-brand-400 bg-brand-50 text-brand-700' : 'border-neutral-200 text-neutral-600'" class="cursor-pointer rounded-lg border px-3 py-2 text-center text-sm font-semibold">
                                    <input type="radio" name="type" value="partial" x-model="type" class="sr-only"> {{ __('Partial') }}
                                </label>
                            </div>
                            <div x-show="type === 'partial'" x-cloak>
                                <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Amount') }} {{ $r['symbol'] }}</label>
                                <input type="number" step="0.01" min="0.01" max="{{ $r['remainingDecimal'] }}" name="amount"
                                    class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" placeholder="0.00" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Reason (optional)') }}</label>
                                <textarea name="reason" rows="3" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" placeholder="{{ __('Tell the seller what went wrong.') }}"></textarea>
                            </div>
                            <div class="flex justify-end gap-2">
                                <button type="button" x-on:click="$dispatch('close-modal', 'refund-request')" class="rounded-lg border border-neutral-200 px-4 py-2 text-sm font-semibold text-neutral-600 transition hover:bg-neutral-50">{{ __('Cancel') }}</button>
                                <x-ui.button type="submit" icon="arrow-uturn-left">{{ __('Submit request') }}</x-ui.button>
                            </div>
                        </form>
                    </x-ui.modal>
                @endif

                <a href="{{ $order['messagesUrl'] }}" class="flex items-center justify-between rounded-xl border border-neutral-200 bg-white px-4 py-3 text-sm font-medium text-neutral-700 transition hover:border-brand-200 hover:bg-brand-50/40">
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-chat-bubble-left-right class="h-4 w-4 text-neutral-400" /> {{ __('Message seller') }}</span>
                    @if ($order['unread'])<span class="h-2 w-2 rounded-full bg-brand-500"></span>@else<x-heroicon-s-chevron-right class="h-4 w-4 text-neutral-300" />@endif
                </a>

                {{-- Trust + discovery --}}
                <div class="rounded-xl border border-neutral-100 bg-neutral-50/60 px-4 py-3">
                    <p class="inline-flex items-center gap-1.5 text-xs font-medium text-neutral-500"><x-heroicon-o-shield-check class="h-4 w-4 text-emerald-500" /> {{ __('Protected by PoishaPay Buyer Protection') }}</p>
                </div>
                <a href="{{ route('home') }}" class="flex items-center justify-between rounded-xl border border-brand-100 bg-gradient-to-r from-brand-50/70 to-white px-4 py-3 text-sm font-medium text-neutral-800 transition hover:border-brand-200">
                    <span class="inline-flex items-center gap-2"><x-heroicon-o-sparkles class="h-4 w-4 text-brand-500" /> {{ __('Discover more products') }}</span>
                    <x-heroicon-s-chevron-right class="h-4 w-4 text-brand-300" />
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
