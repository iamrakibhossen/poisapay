<x-layouts.sales :title="__('Pay with PoisaPay')">
    <div class="min-h-screen bg-neutral-50">
        {{-- PoisaPay-hosted bar --}}
        <header class="border-b border-neutral-200 bg-white">
            <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3.5">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-s-bolt class="h-4 w-4" /></span>
                    <span class="text-sm font-bold text-neutral-900">PoisaPay</span>
                </div>
                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> {{ __('Secure payment') }}</span>
            </div>
        </header>

        <main class="mx-auto max-w-md px-4 py-8">
            {{-- Amount --}}
            <div class="text-center">
                <p class="text-sm text-neutral-500">{{ __('Paying') }} <span class="font-medium text-neutral-700">{{ $seller->displayName() }}</span></p>
                <p class="tabular mt-1 text-4xl font-bold tracking-tight text-neutral-900">{{ $total }}</p>
            </div>

            <div class="mt-6 space-y-4 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                {{-- Order summary --}}
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-600">{{ $product->name }}</span>
                        <span class="tabular font-medium text-neutral-900">{{ $subtotal }}</span>
                    </div>
                    @if ($discount)
                        <div class="flex items-center justify-between text-emerald-600">
                            <span>{{ __('Discount') }} @if ($couponCode)<span class="font-mono text-[11px] uppercase">({{ $couponCode }})</span>@endif</span>
                            <span class="tabular font-medium">−{{ $discount }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t border-neutral-100 pt-2 font-semibold text-neutral-900">
                        <span>{{ __('Total') }}</span>
                        <span class="tabular">{{ $total }}</span>
                    </div>
                </div>

                {{-- Ship-to summary (physical goods) --}}
                @if (! empty($shipping))
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-2 text-xs text-neutral-600">
                                <x-heroicon-o-truck class="mt-0.5 h-4 w-4 shrink-0 text-neutral-400" />
                                <div>
                                    <p class="font-semibold text-neutral-800">{{ __('Ship to') }} · {{ $shipping['name'] }}</p>
                                    <p class="mt-0.5">{{ collect([$shipping['line1'] ?? null, $shipping['line2'] ?? null, $shipping['city'] ?? null, $shipping['postcode'] ?? null])->filter()->implode(', ') }}</p>
                                    <p>{{ $shipping['phone'] ?? '' }}</p>
                                </div>
                            </div>
                            <a href="{{ route('funnel.shipping', ['slug' => $slug]) }}" class="shrink-0 text-xs font-semibold text-brand-600 hover:text-brand-700">{{ __('Edit') }}</a>
                        </div>
                    </div>
                @endif

                {{-- Coupon (GET reloads the page with the discount applied) --}}
                @unless ($ownProduct)
                    <form method="GET" action="{{ route('funnel.pay', ['slug' => $slug]) }}" class="flex items-center gap-2">
                        <input name="coupon" value="{{ $couponCode }}" placeholder="{{ __('Discount code') }}"
                            class="flex-1 rounded-lg border {{ $couponInvalid ? 'border-rose-300' : 'border-neutral-200' }} px-3 py-2 text-sm uppercase focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                        <button type="submit" class="rounded-lg border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-600 transition hover:bg-neutral-50">{{ __('Apply') }}</button>
                    </form>
                    @if ($couponInvalid)<p class="-mt-1 text-xs text-rose-600">{{ __('That code isn’t valid for this order.') }}</p>@endif
                @endunless

                @error('pay')
                    <div class="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ $message }}</div>
                @enderror

                @if ($ownProduct)
                    <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        {{ __('This is your own product — you can’t buy it.') }}
                    </div>
                    <a href="{{ route('funnel.sales', ['slug' => $slug]) }}" class="block text-center text-xs font-medium text-neutral-400 hover:text-neutral-600">← {{ __('Back to the page') }}</a>
                @else
                    {{-- Wallet payment --}}
                    <form method="POST" action="{{ route('funnel.pay.confirm', ['slug' => $slug]) }}" x-data="{ loading: false }" x-on:submit="loading = true">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}" />
                        @if ($couponCode)<input type="hidden" name="coupon_code" value="{{ $couponCode }}" />@endif
                        <div class="rounded-xl border-2 {{ $sufficient ? 'border-brand-500 bg-brand-50/40' : 'border-neutral-200' }} p-3.5">
                            <div class="flex items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-o-wallet class="h-5 w-5" /></span>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-neutral-900">{{ __('PoisaPay Wallet') }}</p>
                                    <p class="tabular text-xs text-neutral-500">{{ __('Balance') }}: {{ $balance }}</p>
                                </div>
                                @if ($sufficient)
                                    <x-heroicon-s-check-circle class="h-5 w-5 text-brand-600" />
                                @endif
                            </div>
                            @unless ($sufficient)
                                <div class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                                    {{ __('Not enough balance — top up with card, crypto, bank or mobile money first.') }}
                                    <a href="{{ route('deposit.index') }}" class="font-semibold underline">{{ __('Add funds') }}</a>
                                </div>
                            @endunless
                        </div>

                        <button type="submit" @disabled(! $sufficient)
                            class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                            x-bind:disabled="loading">
                            <span x-show="! loading" class="flex items-center gap-2"><x-heroicon-o-lock-closed class="h-4 w-4" /> {{ __('Confirm & pay') }} {{ $total }}</span>
                            <span x-show="loading" x-cloak>{{ __('Processing…') }}</span>
                        </button>

                        <div class="mt-3 flex items-center justify-center gap-3 text-[11px] text-neutral-400">
                            <span class="inline-flex items-center gap-1"><x-heroicon-o-shield-check class="h-3.5 w-3.5" /> {{ __('Buyer protection') }}</span>
                            <span>·</span>
                            <span>{{ __('14-day money-back') }}</span>
                        </div>
                    </form>
                @endif

                {{-- Account --}}
                <div class="border-t border-neutral-100 pt-3 text-center">
                    <p class="text-xs text-neutral-400">{{ __('Signed in as') }} <span class="font-medium text-neutral-600">{{ auth()->user()?->email }}</span></p>
                </div>
            </div>

            <a href="{{ route('funnel.sales', ['slug' => $slug]) }}" class="mt-5 block text-center text-xs font-medium text-neutral-400 hover:text-neutral-600">← {{ __('Back to the page') }}</a>
        </main>
    </div>
</x-layouts.sales>
