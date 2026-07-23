@php
    $editing = isset($ad) && $ad;
    $decs = $asset->decimals ?? 6;
    $trim = fn ($v) => str_contains((string) $v, '.') ? rtrim(rtrim((string) $v, '0'), '.') : (string) $v;

    $formAction   = $editing ? route('p2p.ads.update', $ad) : route('p2p.ads.store');
    $sideVal      = $editing ? $ad->side->value : old('side', 'sell');
    $priceTypeVal = old('price_type', $editing ? $ad->price_type->value : 'fixed');
    $fixedPriceVal= old('fixed_price', $editing ? $trim($ad->fixed_price) : '');
    $marginVal    = old('margin_bps', $editing ? $ad->margin_bps : 0);
    $minVal       = old('min_order', $editing ? $trim($ad->min_order) : '');
    $maxVal       = old('max_order', $editing ? $trim($ad->max_order) : '');
    $totalVal     = old('total_amount', $editing ? $trim(\App\Support\Money::ofBase($ad->total_amount, $decs)->toDecimal()) : '');
    $windowVal    = old('payment_window_min', $editing ? $ad->payment_window_min : 15);
    $termsVal     = old('terms', $editing ? $ad->terms : '');
    $selectedMethods = old('payment_method_ids', $editing ? $ad->paymentMethods->pluck('id')->all() : []);

    $locked = null;
    if ($editing) {
        $lm = \App\Support\Money::ofBase($ad->total_amount, $decs, $asset->symbol)
            ->minus(\App\Support\Money::ofBase($ad->available_amount, $decs, $asset->symbol));
        if ($lm->isPositive()) $locked = $lm->format();
    }
@endphp

<x-layouts.app :title="$editing ? __('Edit P2P ad') : __('Post a P2P ad')">
    <div class="mx-auto max-w-2xl space-y-6">
        <x-ui.page-header
            :title="$editing ? __('Edit ad') : __('Post a P2P ad')"
            :subtitle="$editing ? __('Update your offer. Side and asset can\'t be changed once an ad is live.') : __('Advertise to buy or sell USDT. No funds are locked until someone opens an order against your ad.')" />

        @if (session('error'))
            <x-ui.alert type="error">{{ session('error') }}</x-ui.alert>
        @endif

        <x-ui.card>
            <form method="POST" action="{{ $formAction }}" class="space-y-5"
                  x-data="{ priceType: '{{ $priceTypeVal }}' }">
                @csrf
                @if ($editing) @method('PUT') @endif

                {{-- Side --}}
                <div>
                    <label class="pp-label">{{ __('I want to') }}</label>
                    @if ($editing)
                        <div class="flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50 p-3 text-sm">
                            <x-ui.badge :color="$ad->side->color()">{{ $ad->side->label() }}</x-ui.badge>
                            <span class="text-neutral-500">{{ __('Side is locked for a live ad.') }}</span>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-3">
                            @foreach (['sell' => __('Sell USDT (you get escrowed)'), 'buy' => __('Buy USDT')] as $val => $label)
                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-neutral-200 p-3 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">
                                    <input type="radio" name="side" value="{{ $val }}" @checked($sideVal === $val) class="text-brand-500 focus:ring-brand-400">
                                    <span class="font-medium text-neutral-800">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('side')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @endif
                </div>

                {{-- Pricing --}}
                <div>
                    <label class="pp-label">{{ __('Pricing') }}</label>
                    <div class="flex gap-2">
                        <label class="flex-1"><input type="radio" name="price_type" value="fixed" x-model="priceType" class="peer sr-only"><span class="block cursor-pointer rounded-lg border border-neutral-200 py-2 text-center text-sm peer-checked:border-brand-400 peer-checked:bg-brand-50">{{ __('Fixed price') }}</span></label>
                        <label class="flex-1"><input type="radio" name="price_type" value="floating" x-model="priceType" class="peer sr-only"><span class="block cursor-pointer rounded-lg border border-neutral-200 py-2 text-center text-sm peer-checked:border-brand-400 peer-checked:bg-brand-50">{{ __('Floating') }}</span></label>
                    </div>
                </div>

                <div x-show="priceType === 'fixed'">
                    <x-ui.input name="fixed_price" :label="__('Price (BDT per USDT)')" type="text" inputmode="decimal" :value="$fixedPriceVal" placeholder="121.50" :error="$errors->first('fixed_price')" />
                </div>
                <div x-show="priceType === 'floating'" x-cloak>
                    <x-ui.input name="margin_bps" :label="__('Margin (basis points, ± of market)')" type="number" :value="$marginVal" :placeholder="__('100 = +1%')" :error="$errors->first('margin_bps')" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input name="min_order" :label="__('Min order (BDT)')" type="text" inputmode="decimal" :value="$minVal" placeholder="500" :error="$errors->first('min_order')" />
                    <x-ui.input name="max_order" :label="__('Max order (BDT)')" type="text" inputmode="decimal" :value="$maxVal" placeholder="50000" :error="$errors->first('max_order')" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <x-ui.input name="total_amount" :label="__('Total USDT to advertise')" type="text" inputmode="decimal" :value="$totalVal" placeholder="1000" :error="$errors->first('total_amount')" />
                        @if ($locked)
                            <p class="mt-1.5 text-xs text-neutral-500">{{ __(':locked is locked in open orders — total can\'t go below that.', ['locked' => $locked]) }}</p>
                        @endif
                    </div>
                    <x-ui.input name="payment_window_min" :label="__('Payment window (minutes)')" type="number" :value="$windowVal" :error="$errors->first('payment_window_min')" />
                </div>

                {{-- Payment methods (only rails the user has a saved account for) --}}
                <div>
                    <label class="pp-label">{{ __('Payment methods you accept') }}</label>
                    @if ($methods->isEmpty())
                        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4">
                            <p class="text-sm font-semibold text-amber-900">{{ __('Add a payment account first') }}</p>
                            <p class="mt-0.5 text-sm text-amber-700">{{ __('Buyers pay into your saved accounts, so you need at least one before posting an ad.') }}</p>
                            <a href="{{ route('p2p.payment-methods') }}" class="mt-3 inline-block"><x-ui.button size="sm" icon="plus">{{ __('Add payment account') }}</x-ui.button></a>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach ($methods as $m)
                                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2 text-sm has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50">
                                    <input type="checkbox" name="payment_method_ids[]" value="{{ $m->id }}" @checked(in_array($m->id, $selectedMethods)) class="rounded border-gray-300 text-brand-500 focus:ring-brand-400">
                                    <span class="text-neutral-700">{{ $m->name }}</span>
                                </label>
                            @endforeach
                        </div>
                        <p class="mt-1.5 text-xs text-neutral-500">{{ __('Only methods you have a') }} <a href="{{ route('p2p.payment-methods') }}" class="font-medium text-brand-600 hover:underline">{{ __('saved account') }}</a> {{ __('for are listed.') }}</p>
                    @endif
                    @error('payment_method_ids')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <x-ui.textarea name="terms" :label="__('Terms (optional)')" :value="$termsVal" :placeholder="__('Notes for the counterparty…')" rows="3" />

                <div class="flex justify-end gap-3">
                    <a href="{{ route('p2p.ads') }}"><x-ui.button type="button" variant="secondary">{{ __('Cancel') }}</x-ui.button></a>
                    @if ($methods->isEmpty())
                        <a href="{{ route('p2p.payment-methods') }}"><x-ui.button type="button" icon="credit-card">{{ __('Add payment account') }}</x-ui.button></a>
                    @else
                        <x-ui.button type="submit" :icon="$editing ? 'check' : 'megaphone'">{{ $editing ? __('Save changes') : __('Publish ad') }}</x-ui.button>
                    @endif
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.app>
