<x-layouts.sales :title="__('Checkout')" robots="noindex,nofollow">
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

        <main class="mx-auto max-w-md px-4 py-8"
            x-data="{
                bump: false,
                bumpRaw: {{ $bump['amountRaw'] ?? 0 }},
                productBase: {{ $productRaw }},
                discountRaw: {{ $discountRaw }},
                shipFeeRaw: {{ $shipFeeRaw }},
                balanceRaw: {{ $balanceRaw }},
                variants: @js($variantPrices),
                options: @js(($variantPrices[0]['options'] ?? null) ?: (object) []),
                fmt(m) { return (m / Math.pow(10, {{ $assetDecimals }})).toFixed(2) + ' {{ $assetSymbol }}'; },
                get productRaw() {
                    if (! this.variants.length) return this.productBase;
                    const keys = Object.keys(this.options);
                    const m = this.variants.find(v => {
                        const vk = Object.keys(v.options);
                        return vk.length === keys.length && vk.every(k => v.options[k] === this.options[k]);
                    });
                    return m ? m.price : this.productBase;
                },
                get discountApplied() { return Math.min(this.discountRaw, this.productRaw); },
                get totalRaw() { return this.productRaw - this.discountApplied + this.shipFeeRaw + (this.bump ? this.bumpRaw : 0); },
                get totalStr() { return this.fmt(this.totalRaw); },
                get sufficient() { return this.balanceRaw >= this.totalRaw; },
            }">
            {{-- Amount --}}
            <div class="text-center">
                <p class="text-sm text-neutral-500">{{ __('Paying') }} <span class="font-medium text-neutral-700">{{ $seller->displayName() }}</span></p>
                <p class="tabular mt-1 text-4xl font-bold tracking-tight text-neutral-900" x-text="totalStr">{{ $total }}</p>
            </div>

            <div class="mt-6 space-y-4 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                {{-- Order summary --}}
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-600">{{ $product->name }}</span>
                        <span class="tabular font-medium text-neutral-900" x-text="fmt(productRaw)">{{ $subtotal }}</span>
                    </div>
                    @if ($discount)
                        <div class="flex items-center justify-between text-emerald-600">
                            <span>{{ __('Discount') }} @if ($couponCode)<span class="font-mono text-[11px] uppercase">({{ $couponCode }})</span>@endif</span>
                            <span class="tabular font-medium">−{{ $discount }}</span>
                        </div>
                    @endif
                    @if ($shipFee)
                        <div class="flex items-center justify-between text-neutral-600">
                            <span>{{ __('Shipping') }}</span>
                            <span class="tabular font-medium text-neutral-900">{{ $shipFee }}</span>
                        </div>
                    @endif
                    @if ($bump)
                        <div class="flex items-center justify-between text-neutral-600" x-show="bump" x-cloak>
                            <span>＋ {{ $bump['name'] }}</span>
                            <span class="tabular font-medium text-neutral-900">{{ $bump['amount'] }}</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t border-neutral-100 pt-2 font-semibold text-neutral-900">
                        <span>{{ __('Total') }}</span>
                        <span class="tabular" x-text="totalStr">{{ $total }}</span>
                    </div>
                </div>

                {{-- Order bump — one-tap add-on --}}
                @if ($bump)
                    <label class="block cursor-pointer rounded-xl border-2 p-3.5 transition"
                        :class="bump ? 'border-brand-500 bg-brand-50/50' : 'border-dashed border-neutral-300 hover:border-neutral-400'">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" x-model="bump" class="mt-0.5 h-4 w-4 shrink-0 rounded border-neutral-300 text-brand-600 focus:ring-brand-500" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-neutral-900">{{ $bump['headline'] }}</span>
                                    <span class="shrink-0 text-sm font-semibold text-brand-700">+{{ $bump['amount'] }}@if ($bump['compare'])<span class="ms-1 text-xs font-normal text-neutral-400 line-through">{{ $bump['compare'] }}</span>@endif</span>
                                </div>
                                @if ($bump['desc'])<p class="mt-0.5 text-xs text-neutral-500">{{ $bump['desc'] }}</p>@endif
                            </div>
                        </div>
                    </label>
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
                    {{-- Single checkout form: variation + shipping + wallet pay --}}
                    <form method="POST" action="{{ route('funnel.pay.confirm', ['slug' => $slug]) }}" x-data="{ loading: false }" x-on:submit="loading = true" class="space-y-4">
                        @csrf
                        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}" />
                        @if ($couponCode)<input type="hidden" name="coupon_code" value="{{ $couponCode }}" />@endif
                        @if ($bump)<input type="hidden" name="bump" :value="bump ? '1' : '0'" />@endif

                        {{-- Variation (variant products) --}}
                        @if (! empty($variantCatalog))
                            <div class="grid gap-3 sm:grid-cols-2">
                                @foreach ($variantCatalog as $name => $values)
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ $name }}</label>
                                        <select name="options[{{ $name }}]" x-model="options['{{ $name }}']" required
                                            class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                                            @foreach ($values as $v)
                                                <option value="{{ $v }}">{{ $v }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>
                            @error('options')<p class="-mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                        @endif

                        {{-- Shipping address (physical goods) --}}
                        @if ($requiresShipping)
                            <div class="space-y-3 rounded-xl border border-neutral-200 bg-neutral-50/60 p-3.5">
                                <p class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-500"><x-heroicon-o-truck class="h-4 w-4" /> {{ __('Delivery address') }}</p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <input name="name" value="{{ old('name') }}" placeholder="{{ __('Full name') }}" required class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                    <input name="phone" value="{{ old('phone') }}" placeholder="{{ __('Phone') }}" required class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                </div>
                                <input name="line1" value="{{ old('line1') }}" placeholder="{{ __('Address line 1') }}" required class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                <input name="line2" value="{{ old('line2') }}" placeholder="{{ __('Address line 2 (optional)') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                <div class="grid gap-2 sm:grid-cols-3">
                                    <input name="city" value="{{ old('city') }}" placeholder="{{ __('City') }}" required class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                    <input name="postcode" value="{{ old('postcode') }}" placeholder="{{ __('Postcode') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                    <select name="country" required class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                                        @foreach ($countries as $code => $cname)
                                            <option value="{{ $code }}" @selected(old('country', auth()->user()->country ?? 'BD') === $code)>{{ $cname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <textarea name="notes" rows="2" placeholder="{{ __('Delivery notes (optional)') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">{{ old('notes') }}</textarea>
                                @foreach (['name', 'phone', 'line1', 'city', 'country'] as $f)
                                    @error($f)<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                                @endforeach
                            </div>
                        @endif

                        {{-- Wallet --}}
                        <div class="rounded-xl border-2 p-3.5" :class="sufficient ? 'border-brand-500 bg-brand-50/40' : 'border-neutral-200'">
                            <div class="flex items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-o-wallet class="h-5 w-5" /></span>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-neutral-900">{{ __('PoisaPay Wallet') }}</p>
                                    <p class="tabular text-xs text-neutral-500">{{ __('Balance') }}: {{ $balance }}</p>
                                </div>
                                <x-heroicon-s-check-circle class="h-5 w-5 text-brand-600" x-show="sufficient" />
                            </div>
                        </div>

                        <button type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50"
                            x-bind:disabled="loading || ! sufficient">
                            <span x-show="! loading" class="flex items-center gap-2"><x-heroicon-o-lock-closed class="h-4 w-4" /> {{ __('Confirm & pay') }} <span x-text="totalStr"></span></span>
                            <span x-show="loading" x-cloak>{{ __('Processing…') }}</span>
                        </button>
                        <p x-show="! sufficient" x-cloak class="text-center text-xs text-amber-700">
                            {{ __('Not enough balance for this total.') }} <a href="{{ route('deposit.index') }}" class="font-semibold underline">{{ __('Add funds') }}</a>
                        </p>

                        <div class="flex items-center justify-center gap-3 text-[11px] text-neutral-400">
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
