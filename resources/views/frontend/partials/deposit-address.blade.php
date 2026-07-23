{{-- Deposit address + QR (server-rendered; shared by crypto method & network fallback).
     Expects: $address, $addressQr, $addressNetwork, $addressError, $selectedAsset. --}}
<div>
    @if ($addressError)
        <x-ui.alert type="danger" :title="__('Address unavailable')">{{ $addressError }}</x-ui.alert>
    @elseif ($address)
        <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-start" x-data="{ copied: false }">
            <div class="grid h-40 w-40 shrink-0 place-items-center overflow-hidden rounded-xl border border-neutral-200 bg-white p-2 [&>svg]:block [&>svg]:h-full [&>svg]:w-full">{!! $addressQr !!}</div>
            <div class="w-full min-w-0 flex-1">
                <label class="pp-label">{{ __(':network address', ['network' => $addressNetwork]) }}</label>
                <div class="flex items-stretch gap-2">
                    <code class="min-w-0 flex-1 break-all rounded-xl border border-neutral-200 bg-neutral-50 px-3 py-2.5 font-mono text-xs text-neutral-800">{{ $address }}</code>
                    <button type="button"
                        x-on:click="navigator.clipboard.writeText(@js($address)).then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-neutral-200 px-3 text-sm font-medium text-neutral-700 transition hover:border-brand-400 hover:bg-brand-50/40 hover:text-brand-600">
                        <x-heroicon-o-clipboard-document x-show="!copied" class="h-4 w-4" />
                        <x-heroicon-o-check x-show="copied" x-cloak class="h-4 w-4 text-emerald-500" />
                        <span x-text="copied ? 'Copied' : 'Copy'"></span>
                    </button>
                </div>
                <p class="mt-2 text-xs text-neutral-500">
                    {{ __('Send only :symbol on the :network network.', ['symbol' => $selectedAsset->symbol, 'network' => $addressNetwork]) }}
                    {{ __('Deposits are credited after') }} <span class="font-medium text-neutral-700">{{ $selectedAsset->requiredConfirmations() }}</span> {{ __('network confirmations.') }}
                </p>
            </div>
        </div>
    @endif
</div>
