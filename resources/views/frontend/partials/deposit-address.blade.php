{{-- Deposit address + QR (server-rendered; shared by crypto method & network fallback).
     Expects: $address, $addressQr, $addressNetwork, $addressError, $selectedAsset. --}}
<div>
    @if ($addressError)
        <x-ui.alert type="danger" :title="__('Address unavailable')">{{ $addressError }}</x-ui.alert>
    @elseif ($address)
        <div x-data="{ copied: false }"
            class="flex flex-col items-center gap-5 rounded-2xl border border-neutral-200 bg-neutral-50/50 p-5 sm:flex-row sm:items-center">
            {{-- QR --}}
            <div class="grid h-44 w-44 shrink-0 place-items-center overflow-hidden rounded-2xl border border-neutral-200 bg-white p-3 shadow-sm [&>svg]:block [&>svg]:h-full [&>svg]:w-full">{!! $addressQr !!}</div>

            {{-- Address --}}
            <div class="w-full min-w-0 flex-1">
                <span class="mb-2 inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-2.5 py-1 text-xs font-semibold text-brand-700">
                    <span class="h-1.5 w-1.5 rounded-full bg-brand-500"></span> {{ $addressNetwork }}
                </span>
                <label class="pp-label">{{ __(':network address', ['network' => $addressNetwork]) }}</label>
                <div class="flex items-stretch gap-2">
                    <code class="min-w-0 flex-1 break-all rounded-xl border border-neutral-200 bg-white px-3 py-2.5 font-mono text-xs text-neutral-800">{{ $address }}</code>
                    <button type="button"
                        x-on:click="navigator.clipboard.writeText(@js($address)).then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-brand-500 px-3.5 text-sm font-semibold text-white transition hover:bg-brand-600"
                        :aria-label="copied ? @js(__('Copied')) : @js(__('Copy address'))">
                        <x-heroicon-o-clipboard-document x-show="!copied" class="h-4 w-4" />
                        <x-heroicon-o-check x-show="copied" x-cloak class="h-4 w-4" />
                        <span x-text="copied ? @js(__('Copied')) : @js(__('Copy'))">{{ __('Copy') }}</span>
                    </button>
                </div>
                <p class="mt-2.5 flex items-start gap-1.5 text-xs text-neutral-500">
                    <x-heroicon-o-clock class="mt-px h-3.5 w-3.5 shrink-0 text-neutral-400" />
                    <span>{{ __('Send only :symbol on the :network network.', ['symbol' => $selectedAsset->symbol, 'network' => $addressNetwork]) }}
                    {{ __('Credited after') }} <span class="font-medium text-neutral-700">{{ $selectedAsset->requiredConfirmations() }}</span> {{ __('network confirmations.') }}</span>
                </p>
            </div>
        </div>
    @endif
</div>
