<x-layouts.sales :title="__('Shipping details')">
    @php $inp = 'w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500'; $lbl = 'mb-1 block text-xs font-medium text-neutral-600'; @endphp
    <div class="min-h-screen bg-neutral-50">
        {{-- PoisaPay-hosted bar --}}
        <header class="border-b border-neutral-200 bg-white">
            <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3.5">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-s-bolt class="h-4 w-4" /></span>
                    <span class="text-sm font-bold text-neutral-900">PoisaPay</span>
                </div>
                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> {{ __('Secure checkout') }}</span>
            </div>
        </header>

        <main class="mx-auto max-w-4xl px-4 py-8">
            {{-- Steps: shipping → payment --}}
            <div class="mb-6 flex items-center justify-center gap-2 text-xs font-medium">
                <span class="inline-flex items-center gap-1.5 text-brand-600"><span class="grid h-5 w-5 place-items-center rounded-full bg-brand-600 text-[11px] text-white">1</span> {{ __('Shipping') }}</span>
                <span class="h-px w-6 bg-neutral-300"></span>
                <span class="inline-flex items-center gap-1.5 text-neutral-400"><span class="grid h-5 w-5 place-items-center rounded-full bg-neutral-200 text-[11px]">2</span> {{ __('Payment') }}</span>
            </div>

            <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
            <div>
            <div>
                <h1 class="text-lg font-bold tracking-tight text-neutral-900">{{ __('Where should we ship it?') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Enter your delivery address to continue to payment.') }}</p>
            </div>

            <form id="ship-form" method="POST" action="{{ route('funnel.shipping.save', ['slug' => $slug]) }}" class="mt-5 space-y-4 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                @csrf

                {{-- Variation (variant products choose it right here) --}}
                @if (! empty($variantOptions))
                    <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-3.5">
                        <p class="mb-2 text-xs font-semibold text-neutral-700">{{ __('Choose your variation') }}</p>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($variantOptions as $name => $values)
                                <div>
                                    <label class="{{ $lbl }}">{{ $name }}</label>
                                    <select name="options[{{ $name }}]" required class="{{ $inp }}">
                                        @foreach ($values as $v)
                                            <option value="{{ $v }}" @selected(old('options.'.$name, $selectedOptions[$name] ?? '') === $v)>{{ $v }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach
                        </div>
                        @error('options')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div>
                    <label class="{{ $lbl }}">{{ __('Full name') }}</label>
                    <input name="name" value="{{ old('name', $address['name'] ?? '') }}" class="{{ $inp }}" required />
                    @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $lbl }}">{{ __('Phone') }}</label>
                    <input name="phone" value="{{ old('phone', $address['phone'] ?? '') }}" placeholder="+8801XXXXXXXXX" class="{{ $inp }}" required />
                    @error('phone')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="{{ $lbl }}">{{ __('Address') }}</label>
                    <input name="line1" value="{{ old('line1', $address['line1'] ?? '') }}" placeholder="{{ __('House, road, area') }}" class="{{ $inp }}" required />
                    @error('line1')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="{{ $lbl }}">{{ __('City') }}</label>
                        <input name="city" value="{{ old('city', $address['city'] ?? '') }}" class="{{ $inp }}" required />
                        @error('city')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="{{ $lbl }}">{{ __('Postcode') }}</label>
                        <input name="postcode" value="{{ old('postcode', $address['postcode'] ?? '') }}" class="{{ $inp }}" />
                    </div>
                </div>
                <div>
                    <label class="{{ $lbl }}">{{ __('Country') }}</label>
                    <select name="country" class="{{ $inp }}" required>
                        @foreach ($countries as $code => $cname)
                            <option value="{{ $code }}" @selected(old('country', $address['country'] ?? auth()->user()?->country ?? 'BD') === $code)>{{ $cname }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="{{ $lbl }}">{{ __('Delivery notes') }} <span class="text-neutral-400">({{ __('optional') }})</span></label>
                    <textarea name="notes" rows="2" placeholder="{{ __('Landmark, preferred time…') }}" class="{{ $inp }}">{{ old('notes', $address['notes'] ?? '') }}</textarea>
                </div>
            </form>

            <a href="{{ route('funnel.sales', ['slug' => $slug]) }}" class="mt-5 block text-center text-xs font-medium text-neutral-400 hover:text-neutral-600 lg:text-left">← {{ __('Back to the page') }}</a>
            </div>

            {{-- Right: order summary --}}
            <aside class="lg:sticky lg:top-8 lg:self-start">
                <div class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-semibold text-neutral-900">{{ __('Order summary') }}</p>
                    <div class="mt-4 flex items-center gap-3 border-b border-neutral-100 pb-4">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600"><x-heroicon-o-cube class="h-6 w-6" /></span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-neutral-900">{{ $summary['product'] }}</p>
                            @if ($summary['variation'])<p class="text-xs text-neutral-500">{{ $summary['variation'] }}</p>@endif
                            <p class="text-xs text-neutral-400">{{ __('Sold by') }} {{ $seller->displayName() }}</p>
                        </div>
                    </div>
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <dt class="text-neutral-500">{{ __('Product') }}</dt>
                            <dd class="tabular font-medium text-neutral-900">{{ $summary['subtotal'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between">
                            <dt class="text-neutral-500">{{ __('Shipping') }}</dt>
                            <dd class="tabular font-medium {{ $summary['shippingFree'] ? 'text-emerald-600' : 'text-neutral-900' }}">{{ $summary['shipping'] }}</dd>
                        </div>
                        <div class="flex items-center justify-between border-t border-neutral-100 pt-3 text-base font-bold text-neutral-900">
                            <dt>{{ __('Total') }}</dt>
                            <dd class="tabular">{{ $summary['total'] }}</dd>
                        </div>
                    </dl>

                    <button type="submit" form="ship-form" class="mt-5 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-700">
                        {{ __('Continue to payment') }} <x-heroicon-o-arrow-right class="h-4 w-4" />
                    </button>
                    <p class="mt-3 flex items-center justify-center gap-1.5 text-[11px] text-neutral-400">
                        <x-heroicon-o-lock-closed class="h-3.5 w-3.5" /> {{ __('Secure checkout · buyer protection') }}
                    </p>
                </div>
            </aside>
            </div>
        </main>
    </div>
</x-layouts.sales>
