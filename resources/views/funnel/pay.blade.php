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
                <p class="text-sm text-neutral-500">{{ __('Paying') }} <span class="font-medium text-neutral-700">{{ $product['seller']['name'] }}</span></p>
                <p class="tabular mt-1 text-4xl font-bold tracking-tight text-neutral-900">${{ number_format($total, 2) }}</p>
            </div>

            <div class="mt-6 space-y-4 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                {{-- Order summary --}}
                <div class="space-y-2 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-neutral-600">{{ $product['name'] }}</span>
                        <span class="tabular font-medium text-neutral-900">$49.00</span>
                    </div>
                    @if ($bump)
                        <div class="flex items-center justify-between">
                            <span class="text-neutral-600">{{ $product['bump']['name'] }}</span>
                            <span class="tabular font-medium text-neutral-900">$19.00</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between border-t border-neutral-100 pt-2 font-semibold text-neutral-900">
                        <span>{{ __('Total') }}</span>
                        <span class="tabular">${{ number_format($total, 2) }}</span>
                    </div>
                </div>

                {{-- Wallet payment --}}
                <form method="POST" action="{{ route('funnel.pay.confirm', ['slug' => $product['slug']]) }}"
                    x-data="{ enough: {{ $wallet['balance'] >= $total ? 'true' : 'false' }}, loading: false }" x-on:submit="loading = true">
                    @csrf
                    <div class="rounded-xl border-2 border-brand-500 bg-brand-50/40 p-3.5">
                        <div class="flex items-center gap-3">
                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-brand-500 text-white"><x-heroicon-o-wallet class="h-5 w-5" /></span>
                            <div class="flex-1">
                                <p class="text-sm font-semibold text-neutral-900">{{ __('PoisaPay Wallet') }}</p>
                                <p class="tabular text-xs text-neutral-500">{{ __('Balance') }}: ${{ number_format($wallet['balance'], 2) }} {{ $wallet['currency'] }}</p>
                            </div>
                            <x-heroicon-s-check-circle class="h-5 w-5 text-brand-600" />
                        </div>
                        {{-- Insufficient → top up --}}
                        <div x-show="! enough" x-cloak class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
                            {{ __('Not enough balance — top up with card, crypto, bank or mobile money first.') }}
                        </div>
                    </div>

                    <button type="submit" class="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60"
                        x-bind:disabled="loading">
                        <span x-show="! loading" class="flex items-center gap-2"><x-heroicon-o-lock-closed class="h-4 w-4" /> {{ __('Confirm & pay') }} ${{ number_format($total, 2) }}</span>
                        <span x-show="loading" x-cloak>{{ __('Processing…') }}</span>
                    </button>

                    <div class="mt-3 flex items-center justify-center gap-3 text-[11px] text-neutral-400">
                        <span class="inline-flex items-center gap-1"><x-heroicon-o-shield-check class="h-3.5 w-3.5" /> {{ __('Buyer protection') }}</span>
                        <span>·</span>
                        <span>{{ __('14-day money-back') }}</span>
                    </div>
                </form>

                {{-- Other funding / account --}}
                <div class="border-t border-neutral-100 pt-3 text-center">
                    <p class="text-xs text-neutral-400">{{ __('Not you?') }} <a href="{{ route('login') }}" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Use another PoisaPay account') }}</a></p>
                </div>
            </div>

            <a href="{{ route('funnel.sales', ['slug' => $product['slug']]) }}" class="mt-5 block text-center text-xs font-medium text-neutral-400 hover:text-neutral-600">← {{ __('Back to the page') }}</a>
        </main>
    </div>
</x-layouts.sales>
