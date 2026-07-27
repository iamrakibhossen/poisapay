<x-layouts.sales
    :title="$meta['title']"
    :description="$meta['description']"
    :canonical="$meta['canonical']"
    :robots="$meta['robots']"
    :ogImage="$meta['ogImage']"
    :tracking="$tracking"
    :trackingEvents="$trackingEvents"
    ogType="product">
    <x-slot:head>
        @foreach ($schema as $ld)
            <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        @endforeach
        {{-- Compiled block-tree styles: :root design tokens + per-node scoped rules. --}}
        <style>{!! $headCss !!}</style>
    </x-slot:head>

    <div style="font-family: var(--pp-font, Inter, ui-sans-serif, system-ui)"
        class="min-h-screen bg-white pb-20 text-neutral-900 lg:pb-0">
        {{-- The single real checkout form; every buy button references it. Posts to the
             CENTRAL checkout on the platform host — even from a custom domain — so
             payment always happens on one trusted host. CSRF-exempt handoff. --}}
        <form id="buy" method="POST" action="{{ rtrim(config('app.url'), '/') }}/checkout" class="hidden" data-pp-track="cta_click">
            <input type="hidden" name="slug" value="{{ $slug }}">
            {{-- On a custom domain the storefront's clean URL is the root (it serves the
                 same page); on the platform host it's /p/{slug}. --}}
            <input type="hidden" name="return_url"
                value="{{ \App\Shop\Support\PlatformHost::is(request()->getHost()) ? url()->current() : url('/') }}">
        </form>

        {{-- Store top bar (chrome — hidden when the built page has its own header block) --}}
        @unless ($hasHeader ?? false)
            <header class="sticky top-0 z-30 border-b border-neutral-100 bg-white/85 backdrop-blur-md">
                <div class="mx-auto flex max-w-4xl items-center justify-between px-5 py-3">
                    <span class="flex items-center gap-2 text-sm font-bold">
                        @if (! empty($seller['logo']))
                            <img src="{{ $seller['logo'] }}" alt="{{ $seller['name'] }}" class="h-7 w-7 rounded-lg object-cover" />
                        @else
                            <span class="grid h-7 w-7 place-items-center rounded-lg text-[11px] font-bold text-white" style="background: var(--pp-accent)">{{ mb_strtoupper(mb_substr($seller['name'], 0, 1)) }}</span>
                        @endif
                        {{ $seller['name'] }}
                    </span>
                    <button type="submit" form="buy" class="px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-90" style="background: var(--pp-accent); border-radius: var(--pp-btn-radius)">
                        {{ __('Buy now') }} · {{ $product['price'] }}
                    </button>
                </div>
            </header>
        @endunless

        {{-- Buyer-picked variation (product feature — kept as chrome above the built page). --}}
        @if (! empty($variantOptions ?? []))
            <section class="border-t border-neutral-100 py-6">
                <div class="mx-auto max-w-md px-5">
                    @error('buy')
                        <p class="mb-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-center text-xs font-medium text-rose-700">{{ $message }}</p>
                    @enderror
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($variantOptions as $name => $values)
                            <div>
                                <label class="mb-1 block text-xs font-medium text-neutral-500">{{ $name }}</label>
                                <select form="buy" name="options[{{ $name }}]" required
                                    class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-neutral-400 focus:ring-1 focus:ring-neutral-300">
                                    @foreach ($values as $v)
                                        <option value="{{ $v }}" @selected(old('options.'.$name) === $v)>{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>
        @endif

        {{-- ============ The seller's built page (rendered block tree) ============ --}}
        <main>{!! $bodyHtml !!}</main>

        {{-- Trust/footer chrome — hidden when the built page has its own footer block. --}}
        @unless ($hasFooter ?? false)
            <footer class="border-t border-neutral-100 py-8 text-center">
                <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-[11px] text-neutral-400">
                    <span class="inline-flex items-center gap-1"><x-heroicon-o-lock-closed class="h-3.5 w-3.5" /> {{ __('Secure checkout') }}</span>
                    <span class="inline-flex items-center gap-1"><x-heroicon-o-shield-check class="h-3.5 w-3.5" /> {{ __('Buyer protection') }}</span>
                    <span class="inline-flex items-center gap-1"><x-heroicon-o-arrow-uturn-left class="h-3.5 w-3.5" /> {{ __('14-day refund') }}</span>
                </div>
                <p class="mt-3 text-[11px] text-neutral-400">{{ __('Powered by PaishaHub') }}</p>
            </footer>
        @endunless

        {{-- Sticky mobile buy bar (chrome) --}}
        <div class="fixed inset-x-0 bottom-0 z-30 border-t border-neutral-200 bg-white/95 px-4 py-3 backdrop-blur-md lg:hidden">
            <div class="flex items-center justify-between gap-3">
                <div class="leading-tight">
                    <p class="text-sm font-bold">{{ $product['price'] }}
                        @if ($product['comparePrice'])<span class="ms-1 text-xs font-medium text-neutral-400 line-through">{{ $product['comparePrice'] }}</span>@endif
                    </p>
                    <p class="text-[11px] text-neutral-400">{{ __('Secure checkout') }}</p>
                </div>
                <button type="submit" form="buy" class="flex-1 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-90" style="background: var(--pp-accent); border-radius: var(--pp-btn-radius)">
                    {{ __('Buy now') }}
                </button>
            </div>
        </div>
    </div>
</x-layouts.sales>
