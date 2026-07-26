<x-layouts.app :title="__('Custom domains')">
    <div class="mx-auto mt-6 max-w-3xl space-y-5">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('shop') }}" class="mb-2 inline-flex items-center gap-1.5 text-sm font-medium text-neutral-500 transition hover:text-neutral-900"><x-heroicon-o-chevron-left class="h-4 w-4" /> {{ __('Shop') }}</a>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Custom domains') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Give each sales page its own domain instead of /p/{slug}. One domain per sales page.') }}</p>
            </div>
        </div>

        @if (session('success'))
            <x-ui.alert type="success" dismissible>{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert type="danger" dismissible>{{ session('error') }}</x-ui.alert>
        @endif

        @if (! feature('shop_custom_domains', false))
            <x-ui.alert type="warning" :title="__('Custom domains are turned off')">
                {{ __('This feature is not available right now. Contact support if you need it enabled.') }}
            </x-ui.alert>
        @endif

        {{-- Connect a new domain --}}
        @if (feature('shop_custom_domains', false))
            <x-ui.card x-data="{ open: {{ count($domains) ? 'false' : 'true' }} }">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-semibold text-neutral-900">{{ __('Connect a domain') }}</p>
                    <x-ui.button size="sm" variant="secondary" x-on:click="open = ! open" icon="plus">{{ __('New domain') }}</x-ui.button>
                </div>

                <form method="POST" action="{{ route('shop.domains.store') }}" x-show="open" x-cloak class="mt-4">
                    @csrf
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Your domain or subdomain') }}</label>
                            <input name="host" required placeholder="shop.yourbrand.com" value="{{ old('host') }}"
                                class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Points to sales page') }}</label>
                            <select name="sales_page_id" required
                                class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                                <option value="">{{ __('Choose a sales page…') }}</option>
                                @foreach ($availablePages as $p)
                                    <option value="{{ $p->id }}" @selected(old('sales_page_id') === $p->id)>{{ $p->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-3">
                        <x-ui.button type="submit">{{ __('Connect domain') }}</x-ui.button>
                        <p class="text-xs text-neutral-400">{{ __('A subdomain like shop.yourbrand.com is easiest to set up.') }}</p>
                    </div>
                    @if ($availablePages->isEmpty())
                        <p class="mt-3 text-xs text-amber-600">{{ __('Every sales page already has a domain, or you have no pages yet.') }}</p>
                    @endif
                </form>
            </x-ui.card>
        @endif

        {{-- Connected domains --}}
        @forelse ($domains as $d)
            @php($domain = $d['model'])
            <x-ui.card>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-o-globe-alt class="h-5 w-5" /></span>
                        <div>
                            <p class="text-sm font-semibold text-neutral-900">{{ $domain->host }}</p>
                            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-neutral-500">
                                <x-heroicon-o-arrow-turn-down-right class="h-3.5 w-3.5 text-neutral-300" />
                                {{ __('Serves') }} <span class="font-medium text-neutral-700">{{ $domain->salesPage?->name ?? '—' }}</span>
                            </p>
                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                <x-ui.badge :color="$domain->status->color()" dot>{{ __($domain->status->label()) }}</x-ui.badge>
                                <span class="inline-flex items-center gap-1 text-xs {{ $domain->isSslActive() ? 'text-emerald-600' : 'text-neutral-400' }}">
                                    @if ($domain->isSslActive())
                                        <x-heroicon-s-lock-closed class="h-3 w-3" />
                                    @else
                                        <x-heroicon-o-lock-open class="h-3 w-3" />
                                    @endif
                                    {{ __('SSL :status', ['status' => __($domain->ssl_status->label())]) }}
                                </span>
                            </div>
                            <p class="mt-1 text-[11px] text-neutral-400">
                                {{ __('Last checked') }}:
                                <span class="text-neutral-500">{{ $domain->last_checked_at?->diffForHumans() ?? __('never') }}</span>
                            </p>
                            @if ($domain->last_error && ! $domain->isVerified())
                                <p class="mt-1 text-xs text-rose-600">{{ $domain->last_error }}</p>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($domain->isServiceable())
                            <a href="{{ 'https://'.$domain->host }}" target="_blank" rel="noopener"
                                class="rounded-lg p-2 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600" title="{{ __('Open') }}"><x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /></a>
                        @endif
                        <form method="POST" action="{{ route('shop.domains.verify', ['domain' => $domain->id]) }}">
                            @csrf
                            <x-ui.button type="submit" size="sm" variant="secondary" icon="arrow-path">{{ __('Verify again') }}</x-ui.button>
                        </form>
                        <button type="button" x-on:click="$dispatch('open-modal', 'remove-domain-{{ $domain->id }}')"
                            class="rounded-lg p-2 text-neutral-400 transition hover:bg-rose-50 hover:text-rose-600" title="{{ __('Remove') }}"><x-heroicon-o-trash class="h-4 w-4" /></button>
                    </div>
                </div>

                {{-- DNS instructions (shown until verified) --}}
                @unless ($domain->isVerified())
                    <div class="mt-4 overflow-hidden rounded-xl border border-neutral-200">
                        <div class="border-b border-neutral-100 px-4 py-2 text-xs text-neutral-500">
                            {{ __('Add these DNS records at your registrar, then verify.') }}
                        </div>
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                                    <th class="px-4 py-2 text-left">{{ __('Type') }}</th>
                                    <th class="px-4 py-2 text-left">{{ __('Host') }}</th>
                                    <th class="px-4 py-2 text-left">{{ __('Value') }}</th>
                                    <th class="px-4 py-2 text-left">{{ __('TTL') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($d['instructions']['records'] as $rec)
                                    <tr class="border-t border-neutral-100 font-mono text-xs text-neutral-700 {{ $rec['recommended'] ? '' : 'opacity-60' }}">
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $rec['type'] }}</td>
                                        <td class="px-4 py-3"><span class="inline-flex items-center gap-1.5"><span class="break-all">{{ $rec['host'] }}</span><x-ui.copy-text :text="$rec['host']" /></span></td>
                                        <td class="px-4 py-3"><span class="inline-flex items-center gap-1.5"><span class="break-all">{{ $rec['value'] }}</span><x-ui.copy-text :text="$rec['value']" /></span></td>
                                        <td class="px-4 py-3 whitespace-nowrap">{{ $rec['ttl'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-xs text-neutral-400">{{ __('DNS changes can take a few minutes to a few hours to propagate.') }}</p>
                @endunless
            </x-ui.card>

            {{-- Remove confirmation --}}
            <x-ui.confirmation-modal name="remove-domain-{{ $domain->id }}">
                <x-slot:title>{{ __('Remove domain?') }}</x-slot:title>
                <x-slot:content>{{ __(':host will stop serving your sales page. You can reconnect it later.', ['host' => $domain->host]) }}</x-slot:content>
                <x-slot:footer>
                    <x-ui.button variant="secondary" x-on:click="$dispatch('close-modal', 'remove-domain-{{ $domain->id }}')">{{ __('Cancel') }}</x-ui.button>
                    <form method="POST" action="{{ route('shop.domains.destroy', ['domain' => $domain->id]) }}">
                        @csrf
                        @method('DELETE')
                        <x-ui.button type="submit" variant="danger">{{ __('Remove') }}</x-ui.button>
                    </form>
                </x-slot:footer>
            </x-ui.confirmation-modal>
        @empty
            <x-ui.empty-state icon="globe-alt" :title="__('No domains yet')"
                :description="__('Connect a domain above to serve a sales page from your own brand.')" />
        @endforelse

        <x-ui.alert type="info" :title="__('How it works')">
            {{ __('Point a subdomain at') }} <span class="font-mono">{{ $cnameTarget }}</span>
            {{ __('with a CNAME record. We verify ownership by DNS, issue free SSL, and route the domain straight to your sales page. A subdomain like shop.yourbrand.com is recommended.') }}
        </x-ui.alert>
    </div>
</x-layouts.app>
