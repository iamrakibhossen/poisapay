<x-layouts.app :title="__('My Purchases')">
    <div class="mt-6 space-y-5">
        <div class="flex items-center gap-3">
            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600">
                <x-heroicon-o-shopping-bag class="h-5 w-5" />
            </span>
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('My Purchases') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Download your files, track orders and view license keys anytime.') }}</p>
            </div>
        </div>

        @if (session('error'))
            <x-ui.alert type="danger">{{ session('error') }}</x-ui.alert>
        @endif

        @if (count($purchases))
            <div class="space-y-3">
                @foreach ($purchases as $p)
                    <div class="pp-card p-4 sm:p-5" x-data="{ showKey: false, showTrack: false }">
                        <div class="flex flex-wrap items-center gap-4">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600">
                                <x-dynamic-component :component="'heroicon-o-'.$p['icon']" class="h-6 w-6" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('purchases.show', ['order' => $p['orderId']]) }}" class="truncate text-sm font-semibold text-neutral-900 hover:text-brand-600">{{ $p['name'] }}</a>
                                <p class="text-xs text-neutral-500">{{ __('by') }} {{ $p['seller'] }} · {{ $p['date'] }} · {{ $p['price'] }}</p>

                                @if (($p['type'] ?? '') === 'digital')
                                    <p class="mt-1 inline-flex items-center gap-1 text-xs text-neutral-400"><x-heroicon-o-document class="h-3.5 w-3.5" /> {{ $p['file'] ?? __('File pending from seller') }}</p>
                                @elseif (($p['type'] ?? '') === 'physical')
                                    <p class="mt-1 inline-flex items-center gap-2 text-xs">
                                        <x-ui.badge color="info" dot>{{ $p['shipStatus'] }}</x-ui.badge>
                                        <span class="text-neutral-400">{{ __('Tracking') }}: <span class="font-mono text-neutral-500">{{ $p['tracking'] }}</span></span>
                                    </p>
                                @endif
                            </div>

                            <div class="shrink-0">
                                @if (($p['type'] ?? '') === 'license')
                                    <x-ui.button x-on:click="showKey = ! showKey" variant="secondary" size="sm" icon="key">{{ $p['action'] }}</x-ui.button>
                                @elseif (($p['type'] ?? '') === 'physical')
                                    <x-ui.button x-on:click="showTrack = ! showTrack" variant="secondary" size="sm" icon="truck">{{ $p['action'] }}</x-ui.button>
                                @elseif (($p['type'] ?? '') === 'digital')
                                    @if (! empty($p['downloadUrl']))
                                        <x-ui.button href="{{ $p['downloadUrl'] }}" size="sm" icon="arrow-down-tray">{{ $p['action'] }}</x-ui.button>
                                    @else
                                        <x-ui.button size="sm" icon="clock" variant="secondary" disabled>{{ __('File pending') }}</x-ui.button>
                                    @endif
                                @else
                                    <x-ui.button size="sm" variant="secondary" icon="arrow-top-right-on-square">{{ $p['action'] ?? __('View') }}</x-ui.button>
                                @endif
                            </div>
                        </div>

                        {{-- License key reveal --}}
                        @if (($p['type'] ?? '') === 'license')
                            <div x-show="showKey" x-cloak x-transition class="mt-3 flex items-center gap-2 rounded-xl border border-neutral-200 bg-neutral-50/60 p-3">
                                @if (! empty($p['licenseKey']))
                                    <code class="flex-1 truncate font-mono text-sm text-neutral-800" x-ref="key-{{ $loop->index }}">{{ $p['licenseKey'] }}</code>
                                    <button type="button" x-on:click="navigator.clipboard.writeText($refs['key-{{ $loop->index }}'].textContent)"
                                        class="rounded-md bg-white px-2 py-1 text-xs font-medium text-neutral-500 ring-1 ring-neutral-200 hover:text-brand-600">{{ __('Copy') }}</button>
                                @else
                                    <p class="flex-1 text-sm text-neutral-500">{{ __('Your key is being issued — it’ll appear here shortly.') }}</p>
                                @endif
                            </div>
                        @endif

                        {{-- Shipment tracking (physical) --}}
                        @if (($p['type'] ?? '') === 'physical')
                            <div x-show="showTrack" x-cloak x-transition class="mt-3 rounded-xl border border-neutral-200 bg-neutral-50/60 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <span class="font-medium text-neutral-700">{{ __('Carrier') }}: {{ $p['carrier'] }} · <span class="font-mono">{{ $p['tracking'] }}</span></span>
                                    <span class="text-neutral-400">{{ __('Ship to') }}: {{ $p['address'] }}</span>
                                </div>
                                <div class="mt-4 space-y-4">
                                    @foreach ($p['timeline'] as $i => [$label, $at, $done])
                                        <div class="flex items-center gap-3">
                                            <span @class([
                                                'grid h-6 w-6 shrink-0 place-items-center rounded-full text-[11px]',
                                                'bg-brand-500 text-white' => $done,
                                                'bg-neutral-200 text-neutral-400' => ! $done,
                                            ])>
                                                @if ($done)<x-heroicon-s-check class="h-3.5 w-3.5" />@else{{ $i + 1 }}@endif
                                            </span>
                                            <p class="flex-1 text-sm font-medium {{ $done ? 'text-neutral-900' : 'text-neutral-400' }}">{{ $label }}</p>
                                            <span class="text-xs text-neutral-400">{{ $at }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        {{-- Review — leave one, or show yours --}}
                        @if (! empty($p['canReview']))
                            <div class="mt-3 border-t border-neutral-100 pt-3" x-data="{ open: {{ $errors->has('review') ? 'true' : 'false' }}, rating: {{ $p['review']['rating'] ?? 0 }} }">
                                @if ($p['review'])
                                    <div class="rounded-xl bg-neutral-50/70 p-3">
                                        <div class="flex items-center gap-1 text-amber-500">
                                            @for ($s = 1; $s <= 5; $s++)<span>{{ $s <= $p['review']['rating'] ? '★' : '☆' }}</span>@endfor
                                            <span class="ml-2 text-xs font-medium text-neutral-500">{{ __('Your review') }}</span>
                                        </div>
                                        @if ($p['review']['body'])<p class="mt-1 text-xs text-neutral-600">{{ $p['review']['body'] }}</p>@endif
                                        @if ($p['review']['reply'])
                                            <div class="mt-2 rounded-lg border-l-2 border-brand-300 bg-white px-3 py-2">
                                                <p class="text-[11px] font-semibold text-brand-700">{{ __('Seller replied') }}</p>
                                                <p class="text-xs text-neutral-600">{{ $p['review']['reply'] }}</p>
                                            </div>
                                        @endif
                                        <button type="button" x-on:click="open = ! open" class="mt-2 text-[11px] font-medium text-brand-600 hover:text-brand-700">{{ __('Edit review') }}</button>
                                    </div>
                                @else
                                    <button type="button" x-on:click="open = ! open" class="inline-flex items-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700"><x-heroicon-o-star class="h-3.5 w-3.5" /> {{ __('Leave a review') }}</button>
                                @endif

                                <form method="POST" action="{{ route('purchases.review', ['order' => $p['orderId']]) }}" x-show="open" x-cloak class="mt-3 space-y-2">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $p['productId'] }}" />
                                    <div class="flex items-center gap-1">
                                        <template x-for="s in [1,2,3,4,5]" :key="s">
                                            <button type="button" x-on:click="rating = s" class="text-xl" :class="s <= rating ? 'text-amber-500' : 'text-neutral-300'">★</button>
                                        </template>
                                        <input type="hidden" name="rating" :value="rating" />
                                    </div>
                                    <input name="title" value="{{ old('title') }}" maxlength="160" placeholder="{{ __('Title (optional)') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                    <textarea name="body" rows="2" maxlength="2000" placeholder="{{ __('Share what you thought…') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">{{ old('body') }}</textarea>
                                    @error('review')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                                    <x-ui.button type="submit" size="sm" icon="paper-airplane" x-bind:disabled="! rating">{{ __('Submit review') }}</x-ui.button>
                                </form>
                            </div>
                        @endif

                        {{-- Quick links --}}
                        <div class="mt-3 flex flex-wrap gap-4 border-t border-neutral-100 pt-3 text-xs">
                            <a href="{{ route('purchases.show', ['order' => $p['orderId']]) }}" class="inline-flex items-center gap-1 font-medium text-neutral-500 hover:text-brand-600"><x-heroicon-o-document-magnifying-glass class="h-3.5 w-3.5" /> {{ __('View details') }}</a>
                            <a href="{{ $p['messagesUrl'] }}" class="inline-flex items-center gap-1 font-medium text-neutral-500 hover:text-brand-600"><x-heroicon-o-chat-bubble-left-right class="h-3.5 w-3.5" /> {{ __('Message seller') }}@if (! empty($p['unread']))<span class="ml-0.5 inline-block h-1.5 w-1.5 rounded-full bg-brand-500"></span>@endif</a>
                            <a href="#" class="inline-flex items-center gap-1 font-medium text-neutral-500 hover:text-rose-600"><x-heroicon-o-arrow-uturn-left class="h-3.5 w-3.5" /> {{ __('Request refund') }}</a>
                        </div>
                    </div>

                    {{-- Contact seller modal --}}
                    <x-ui.modal name="contact-{{ $loop->index }}" :title="__('Contact seller')" :subtitle="$p['seller']" maxWidth="lg">
                        <div x-data="{ sent: false }">
                            <div x-show="! sent" class="space-y-4">
                                <div class="flex items-center gap-3 rounded-xl border border-neutral-200 bg-neutral-50/60 p-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-dynamic-component :component="'heroicon-o-'.$p['icon']" class="h-5 w-5" /></span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-neutral-900">{{ $p['name'] }}</p>
                                        <p class="text-xs text-neutral-400">{{ __('Purchased') }} {{ $p['date'] }} · {{ $p['price'] }}</p>
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Subject') }}</label>
                                    <select class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                                        <option>{{ __('Question about my order') }}</option>
                                        <option>{{ __('Problem with the product') }}</option>
                                        <option>{{ __('Shipping / delivery') }}</option>
                                        <option>{{ __('Refund request') }}</option>
                                        <option>{{ __('Other') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Message') }}</label>
                                    <textarea rows="4" placeholder="{{ __('Write your message to the seller…') }}" class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500"></textarea>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-[11px] text-neutral-400">{{ __('The seller usually replies within a day.') }}</p>
                                    <x-ui.button x-on:click="sent = true" icon="paper-airplane">{{ __('Send message') }}</x-ui.button>
                                </div>
                            </div>

                            {{-- Sent state --}}
                            <div x-show="sent" x-cloak class="py-6 text-center">
                                <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-emerald-100 text-emerald-600"><x-heroicon-o-check class="h-6 w-6" /></span>
                                <p class="mt-3 text-sm font-semibold text-neutral-900">{{ __('Message sent') }}</p>
                                <p class="mt-1 text-xs text-neutral-500">{{ __('We’ve notified :seller — replies land in your notifications.', ['seller' => $p['seller']]) }}</p>
                            </div>
                        </div>
                    </x-ui.modal>
                @endforeach
            </div>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="shopping-bag" :title="__('No purchases yet')" :description="__('Products you buy will appear here for download and access.')" />
            </div>
        @endif
    </div>
</x-layouts.app>
