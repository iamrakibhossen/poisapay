<x-layouts.app :title="__('Pay Invoice')">
    @php $initial = mb_strtoupper(mb_substr($merchantName, 0, 1)) ?: 'M'; @endphp
    <div class="mx-auto max-w-lg space-y-6">
        <x-ui.page-header :title="__('Pay invoice')" :subtitle="__('Review the request and confirm payment from your balance.')" />

        <x-ui.card>
            {{-- Merchant identity --}}
            <div class="flex items-center gap-3 border-b border-neutral-100 pb-4">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-lg font-bold text-white shadow-sm">{{ $initial }}</span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <p class="truncate text-sm font-semibold text-neutral-900">{{ $merchantName }}</p>
                        @if ($business && $business['verified'])
                            <span class="inline-flex shrink-0 items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700" title="{{ __('Verified merchant') }}">
                                <x-heroicon-s-check-badge class="h-3.5 w-3.5" /> {{ __('Verified') }}
                            </span>
                        @endif
                    </div>
                    <p class="truncate text-xs text-neutral-500">
                        {{ $business['category'] ?? __('Merchant') }}
                        @if ($business && $business['website'])
                            · <a href="{{ $business['website'] }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">{{ preg_replace('#^https?://#', '', $business['website']) }}</a>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Amount --}}
            <div class="flex flex-col items-center gap-1 py-6">
                <p class="text-[11px] font-medium uppercase tracking-wide text-neutral-400">{{ __('Amount due') }}</p>
                <p class="tabular text-3xl font-bold tracking-tight text-neutral-900">{{ $amount }}</p>
                <p class="text-sm text-neutral-500">{{ __('Payment to') }} <span class="font-medium text-neutral-700">{{ $merchantName }}</span></p>
            </div>

            <dl class="divide-y divide-neutral-100 border-t border-neutral-100">
                <div class="flex items-center justify-between py-3">
                    <dt class="text-sm text-neutral-500">{{ __('Reference') }}</dt>
                    <dd class="font-mono text-sm text-neutral-800">{{ $reference }}</dd>
                </div>
                @if ($memo)
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-sm text-neutral-500">{{ __('Memo') }}</dt>
                        <dd class="text-sm text-neutral-800">{{ $memo }}</dd>
                    </div>
                @endif
                <div class="flex items-center justify-between py-3">
                    <dt class="text-sm text-neutral-500">{{ __('Status') }}</dt>
                    <dd>
                        <span @class([
                            'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                            'bg-emerald-50 text-emerald-700' => $statusColor === 'success',
                            'bg-amber-50 text-amber-700' => $statusColor === 'warning',
                            'bg-red-50 text-red-700' => $statusColor === 'danger',
                            'bg-neutral-100 text-neutral-600' => $statusColor === 'gray',
                        ])>
                            <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                            {{ $statusLabel }}
                        </span>
                    </dd>
                </div>
                @if (! $isMerchant && $available)
                    <div class="flex items-center justify-between py-3">
                        <dt class="text-sm text-neutral-500">{{ __('Your balance') }}</dt>
                        <dd class="tabular text-sm font-medium text-neutral-700">{{ $available }}</dd>
                    </div>
                @endif
            </dl>

            <div class="pt-4">
                @if ($isPaid)
                    <x-ui.alert type="success" :title="__('Paid')">
                        {{ $paidAtHuman ? __('This invoice was paid :when. No further action is required.', ['when' => $paidAtHuman]) : __('This invoice was paid. No further action is required.') }}
                    </x-ui.alert>
                @elseif ($isExpired)
                    <x-ui.alert type="warning" :title="__('Invoice expired')">
                        {{ __('This payment request has expired. Ask the merchant to issue a new invoice.') }}
                    </x-ui.alert>
                @elseif ($isCancelled)
                    <x-ui.alert type="danger" :title="__('Invoice cancelled')">
                        {{ __('This invoice was cancelled and can no longer be paid.') }}
                    </x-ui.alert>
                @elseif ($isMerchant)
                    <x-ui.alert type="info" :title="__('This is your invoice')">
                        {{ __("You created this invoice, so you can't pay it. Share the link with a customer instead.") }}
                    </x-ui.alert>
                @elseif ($isPayable)
                    @if ($errors->has('invoice'))
                        <x-ui.alert type="danger" :title="__('Payment failed')" class="mb-4">{{ $errors->first('invoice') }}</x-ui.alert>
                    @endif

                    <x-ui.button type="button" class="w-full" icon="bolt"
                        x-on:click="$dispatch('open-modal', 'pay-invoice')">{{ __('Pay :amount', ['amount' => $amount]) }}</x-ui.button>

                    <x-ui.modal name="pay-invoice" :title="__('Confirm payment')" maxWidth="md">
                        <div class="flex flex-col items-center gap-1 py-2 text-center">
                            <p class="text-sm text-neutral-500">{{ __("You're about to pay") }}</p>
                            <p class="tabular text-[2rem] font-semibold leading-tight tracking-tight text-neutral-900">{{ $amount }}</p>
                            <p class="text-sm text-neutral-600">{{ __('to') }} <span class="font-medium text-neutral-800">{{ $merchantName }}</span></p>
                            @if ($available)
                                <p class="mt-3 rounded-full bg-neutral-100 px-3 py-1 text-xs text-neutral-500">{{ __('Paid from your balance · :balance', ['balance' => $available]) }}</p>
                            @endif
                        </div>

                        <x-slot:footer>
                            <x-ui.button type="button" variant="secondary"
                                x-on:click="$dispatch('close-modal', 'pay-invoice')">{{ __('Cancel') }}</x-ui.button>
                            <form method="POST" action="{{ route('pay.execute', $invoice->id) }}">
                                @csrf
                                <x-ui.button type="submit" icon="bolt">{{ __('Pay Now') }}</x-ui.button>
                            </form>
                        </x-slot:footer>
                    </x-ui.modal>
                @endif
            </div>
        </x-ui.card>

        @if ($business && $business['supportEmail'])
            <p class="text-center text-xs text-neutral-400">
                {{ __('Questions about this payment?') }}
                <a href="mailto:{{ $business['supportEmail'] }}" class="font-medium text-neutral-500 hover:text-brand-600">{{ $business['supportEmail'] }}</a>
            </p>
        @endif
    </div>
</x-layouts.app>
