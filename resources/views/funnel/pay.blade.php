<x-layouts.sales :title="__('Checkout')" robots="noindex,nofollow">
    <div class="min-h-screen bg-neutral-50 pb-24 sm:pb-0">
        {{-- Secure hosted bar --}}
        <header class="sticky top-0 z-20 border-b border-neutral-200 bg-white/90 backdrop-blur">
            <div class="mx-auto flex max-w-lg items-center justify-between px-4 py-3">
                <div class="flex items-center gap-2">
                    <span class="grid h-7 w-7 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-s-bolt class="h-4 w-4" /></span>
                    <span class="text-sm font-bold text-neutral-900">PoisaPay</span>
                    <span class="text-neutral-300">·</span>
                    <span class="text-xs text-neutral-500">{{ __('Checkout') }}</span>
                </div>
                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> {{ __('Secure') }}</span>
            </div>
        </header>

        <main class="mx-auto max-w-lg px-4 py-8"
            x-data="{
                loading: false,
                bump: false,
                showCoupon: {{ $couponCode || $couponInvalid ? 'true' : 'false' }},
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

            {{-- Merchant + amount --}}
            <div class="text-center">
                <div class="mx-auto mb-3 flex items-center justify-center gap-2">
                    @if ($seller->logoUrl())
                        <img src="{{ $seller->logoUrl() }}" alt="{{ $seller->displayName() }}" class="h-8 w-8 rounded-lg object-cover ring-1 ring-neutral-200" />
                    @else
                        <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-500 text-sm font-bold text-white">{{ mb_strtoupper(mb_substr($seller->displayName(), 0, 1)) }}</span>
                    @endif
                    <span class="text-sm font-medium text-neutral-600">{{ $seller->displayName() }}</span>
                </div>
                <p class="text-xs uppercase tracking-wider text-neutral-400">{{ __('Total') }}</p>
                <p class="tabular mt-1 text-4xl font-extrabold tracking-tight text-neutral-900" x-text="totalStr">{{ $total }}</p>
            </div>

            @error('pay')
                <div class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $message }}</div>
            @enderror

            @if ($ownProduct)
                <div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-5 text-center text-sm text-amber-700">
                    {{ __('This is your own product — you can’t buy it.') }}
                    <a href="{{ route('funnel.sales', ['slug' => $slug]) }}" class="mt-2 block font-semibold text-neutral-600 hover:text-neutral-900">← {{ __('Back to the page') }}</a>
                </div>
            @else
                {{-- ═══ Order summary ═══ --}}
                <div class="mt-6 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <div class="flex items-start gap-3">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-heroicon-o-cube class="h-5 w-5" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-neutral-900">{{ $product->name }}</p>
                            @if (! empty($variantCatalog))
                                <p class="text-xs text-neutral-400" x-text="Object.values(options).join(' · ')"></p>
                            @endif
                        </div>
                        <span class="tabular text-sm font-semibold text-neutral-900" x-text="fmt(productRaw)">{{ $subtotal }}</span>
                    </div>

                    {{-- Variation — chosen right on the product --}}
                    @if (! empty($variantCatalog))
                        <div class="mt-4 space-y-3 border-t border-neutral-100 pt-4">
                            @foreach ($variantCatalog as $name => $values)
                                <div>
                                    <p class="mb-1.5 text-xs font-medium text-neutral-600">{{ $name }}</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach ($values as $v)
                                            <button type="button" x-on:click="options['{{ $name }}'] = @js($v)"
                                                :class="options['{{ $name }}'] === @js($v) ? 'border-brand-500 bg-brand-50 text-brand-700 ring-1 ring-brand-500' : 'border-neutral-200 text-neutral-600 hover:border-neutral-300'"
                                                class="rounded-lg border px-3.5 py-2 text-sm font-medium transition">{{ $v }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                            @error('options')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                        </div>
                    @endif

                    <dl class="mt-4 space-y-1.5 border-t border-neutral-100 pt-3 text-sm">
                        @if ($discount)
                            <div class="flex items-center justify-between text-emerald-600">
                                <dt>{{ __('Discount') }} @if ($couponCode)<span class="font-mono text-[11px] uppercase">({{ $couponCode }})</span>@endif</dt>
                                <dd class="tabular font-medium">−{{ $discount }}</dd>
                            </div>
                        @endif
                        @if ($shipFee)
                            <div class="flex items-center justify-between text-neutral-500">
                                <dt>{{ __('Shipping') }}</dt><dd class="tabular">{{ $shipFee }}</dd>
                            </div>
                        @endif
                        @if ($bump)
                            <div class="flex items-center justify-between text-neutral-500" x-show="bump" x-cloak>
                                <dt>＋ {{ $bump['name'] }}</dt><dd class="tabular">{{ $bump['amount'] }}</dd>
                            </div>
                        @endif
                        <div class="flex items-center justify-between border-t border-neutral-100 pt-2 text-base font-bold text-neutral-900">
                            <dt>{{ __('Total') }}</dt><dd class="tabular" x-text="totalStr">{{ $total }}</dd>
                        </div>
                    </dl>

                    {{-- Coupon (collapsible; GET reload applies it) --}}
                    <div class="mt-3 border-t border-neutral-100 pt-3">
                        <button type="button" x-show="! showCoupon" x-on:click="showCoupon = true" class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700">
                            <x-heroicon-o-tag class="h-3.5 w-3.5" /> {{ __('Have a discount code?') }}
                        </button>
                        <form x-show="showCoupon" x-cloak method="GET" action="{{ route('funnel.pay', ['slug' => $slug]) }}" class="flex items-center gap-2">
                            <input name="coupon" value="{{ $couponCode }}" placeholder="{{ __('Discount code') }}"
                                class="flex-1 rounded-lg border {{ $couponInvalid ? 'border-rose-300' : 'border-neutral-200' }} px-3 py-2 text-sm uppercase focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                            <button type="submit" class="rounded-lg bg-neutral-900 px-3.5 py-2 text-sm font-medium text-white transition hover:bg-neutral-700">{{ __('Apply') }}</button>
                        </form>
                        @if ($couponInvalid)<p class="mt-1 text-xs text-rose-600">{{ __('That code isn’t valid for this order.') }}</p>@endif
                    </div>
                </div>

                {{-- Order bump --}}
                @if ($bump)
                    <label class="mt-4 block cursor-pointer rounded-2xl border-2 p-4 transition"
                        :class="bump ? 'border-brand-500 bg-brand-50/50 shadow-sm' : 'border-dashed border-neutral-300 hover:border-neutral-400'">
                        <div class="flex items-start gap-3">
                            <input type="checkbox" x-model="bump" class="mt-0.5 h-4 w-4 shrink-0 rounded border-neutral-300 text-brand-600 focus:ring-brand-500" />
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold text-neutral-900">{{ $bump['headline'] }}</span>
                                    <span class="shrink-0 text-sm font-bold text-brand-700">+{{ $bump['amount'] }}@if ($bump['compare'])<span class="ms-1 text-xs font-normal text-neutral-400 line-through">{{ $bump['compare'] }}</span>@endif</span>
                                </div>
                                @if ($bump['desc'])<p class="mt-0.5 text-xs text-neutral-500">{{ $bump['desc'] }}</p>@endif
                            </div>
                        </div>
                    </label>
                @endif

                {{-- ═══ Checkout form: variation + shipping + wallet ═══ --}}
                <form id="checkout" method="POST" action="{{ route('funnel.pay.confirm', ['slug' => $slug]) }}" x-on:submit="loading = true" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}" />
                    @if ($couponCode)<input type="hidden" name="coupon_code" value="{{ $couponCode }}" />@endif
                    @if ($bump)<input type="hidden" name="bump" :value="bump ? '1' : '0'" />@endif

                    {{-- Chosen variation travels with the form (selected on the product above). --}}
                    @foreach ($variantCatalog as $name => $values)
                        <input type="hidden" name="options[{{ $name }}]" :value="options['{{ $name }}']" />
                    @endforeach

                    {{-- Shipping address --}}
                    @if ($requiresShipping)
                        <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                            <p class="mb-3 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-neutral-500"><x-heroicon-o-truck class="h-4 w-4" /> {{ __('Delivery address') }}</p>
                            <div class="space-y-2.5">
                                <div class="grid gap-2.5 sm:grid-cols-2">
                                    <input name="name" value="{{ old('name') }}" placeholder="{{ __('Full name') }}" required class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                    <input name="phone" value="{{ old('phone') }}" placeholder="{{ __('Phone') }}" required class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                </div>
                                <input name="line1" value="{{ old('line1') }}" placeholder="{{ __('Address line 1') }}" required class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                <input name="line2" value="{{ old('line2') }}" placeholder="{{ __('Address line 2 (optional)') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                <div class="grid gap-2.5 sm:grid-cols-3">
                                    <input name="city" value="{{ old('city') }}" placeholder="{{ __('City') }}" required class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                    <input name="postcode" value="{{ old('postcode') }}" placeholder="{{ __('Postcode') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                    <select name="country" required class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                                        @foreach ($countries as $code => $cname)
                                            <option value="{{ $code }}" @selected(old('country', auth()->user()->country ?? 'BD') === $code)>{{ $cname }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <textarea name="notes" rows="2" placeholder="{{ __('Delivery notes (optional)') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">{{ old('notes') }}</textarea>
                            </div>
                            @foreach (['name', 'phone', 'line1', 'city', 'country'] as $f)
                                @error($f)<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                            @endforeach
                        </div>
                    @endif

                    {{-- Payment method --}}
                    <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ __('Payment') }}</p>
                        <div class="rounded-xl border-2 p-3.5 transition" :class="sufficient ? 'border-brand-500 bg-brand-50/40' : 'border-amber-200 bg-amber-50/40'">
                            <div class="flex items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-o-wallet class="h-5 w-5" /></span>
                                <div class="flex-1">
                                    <p class="text-sm font-semibold text-neutral-900">{{ __('PoisaPay Wallet') }}</p>
                                    <p class="tabular text-xs text-neutral-500">{{ __('Balance') }}: {{ $balance }}</p>
                                </div>
                                <x-heroicon-s-check-circle class="h-5 w-5 text-brand-600" x-show="sufficient" />
                            </div>
                            <div x-show="! sufficient" x-cloak class="mt-3 rounded-lg border border-amber-200 bg-white px-3 py-2 text-xs text-amber-700">
                                {{ __('Not enough balance for this total.') }} <a href="{{ route('deposit.index') }}" class="font-semibold underline">{{ __('Add funds') }}</a>
                            </div>
                        </div>
                    </div>

                    {{-- Desktop pay button --}}
                    <button type="submit"
                        class="hidden w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50 sm:flex"
                        x-bind:disabled="loading || ! sufficient">
                        <span x-show="! loading" class="flex items-center gap-2"><x-heroicon-o-lock-closed class="h-4 w-4" /> {{ __('Pay') }} <span x-text="totalStr"></span></span>
                        <span x-show="loading" x-cloak>{{ __('Processing…') }}</span>
                    </button>

                    <div class="flex items-center justify-center gap-3 text-[11px] text-neutral-400">
                        <span class="inline-flex items-center gap-1"><x-heroicon-o-shield-check class="h-3.5 w-3.5" /> {{ __('Buyer protection') }}</span>
                        <span>·</span>
                        <span class="inline-flex items-center gap-1"><x-heroicon-o-arrow-uturn-left class="h-3.5 w-3.5" /> {{ __('14-day money-back') }}</span>
                        <span>·</span>
                        <span class="inline-flex items-center gap-1"><x-heroicon-o-lock-closed class="h-3.5 w-3.5" /> {{ __('Encrypted') }}</span>
                    </div>
                </form>

                <p class="mt-4 text-center text-xs text-neutral-400">{{ __('Signed in as') }} <span class="font-medium text-neutral-500">{{ auth()->user()?->email }}</span></p>
            @endif

            <a href="{{ route('funnel.sales', ['slug' => $slug]) }}" class="mt-6 block text-center text-xs font-medium text-neutral-400 hover:text-neutral-600">← {{ __('Back to the page') }}</a>
        </main>

        {{-- Sticky mobile pay bar --}}
        @unless ($ownProduct)
            <div class="fixed inset-x-0 bottom-0 z-30 border-t border-neutral-200 bg-white/95 px-4 py-3 backdrop-blur sm:hidden"
                x-data x-show="true">
                <button type="submit" form="checkout"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-3.5 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-700 disabled:opacity-50"
                    x-bind:disabled="loading || ! sufficient">
                    <span x-show="! loading" class="flex items-center gap-2"><x-heroicon-o-lock-closed class="h-4 w-4" /> {{ __('Pay') }} <span x-text="totalStr"></span></span>
                    <span x-show="loading" x-cloak>{{ __('Processing…') }}</span>
                </button>
            </div>
        @endunless
    </div>
</x-layouts.sales>
