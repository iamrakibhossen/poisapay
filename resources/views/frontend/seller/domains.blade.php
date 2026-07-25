<x-layouts.app :title="__('Custom domains')">
    <div class="mx-auto mt-6 max-w-3xl space-y-5"
        x-data="{ adding: false, newDomain: '', newPage: '', added: null, addedPage: '', connect() { const d = this.newDomain.trim(); if (d && this.newPage) { this.added = d; this.addedPage = this.newPage; this.adding = false; this.newDomain = ''; } } }">

        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Custom domains') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Give each sales page its own domain instead of /p/{slug}. One domain per sales page.') }}</p>
            </div>
            <x-ui.button x-on:click="adding = ! adding" icon="plus">{{ __('Connect domain') }}</x-ui.button>
        </div>

        {{-- Add form: domain + which sales page it serves --}}
        <div x-show="adding" x-cloak>
            <x-ui.card>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Your domain or subdomain') }}</label>
                        <input x-model="newDomain" x-on:keydown.enter.prevent="connect()" placeholder="shop.yourbrand.com"
                            class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Points to sales page') }}</label>
                        <select x-model="newPage" class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500">
                            <option value="">{{ __('Choose a sales page…') }}</option>
                            @foreach ($availablePages as $p)
                                <option value="{{ $p }}">{{ $p }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-3">
                    <x-ui.button x-on:click="connect()">{{ __('Add domain') }}</x-ui.button>
                    <p class="text-xs text-neutral-400">{{ __('A subdomain like shop.yourbrand.com is easiest to set up.') }}</p>
                </div>
            </x-ui.card>
        </div>

        {{-- DNS setup shown after adding --}}
        <template x-if="added">
            <x-ui.card class="border-brand-200">
                <div class="mb-3 flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-lg bg-amber-50 text-amber-600"><x-heroicon-o-arrow-path class="h-4 w-4" /></span>
                    <div>
                        <p class="text-sm font-semibold text-neutral-900"><span x-text="added"></span> <span class="font-normal text-neutral-400">→</span> <span class="text-neutral-600" x-text="addedPage"></span></p>
                        <p class="text-xs text-neutral-500">{{ __('Add this DNS record at your registrar, then verify.') }}</p>
                    </div>
                </div>
                <div class="overflow-hidden rounded-xl border border-neutral-200">
                    <table class="min-w-full text-sm">
                        <thead><tr class="bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400"><th class="px-4 py-2 text-left">{{ __('Type') }}</th><th class="px-4 py-2 text-left">{{ __('Name') }}</th><th class="px-4 py-2 text-left">{{ __('Value') }}</th><th class="px-4 py-2 text-left">{{ __('TTL') }}</th></tr></thead>
                        <tbody>
                            <tr class="border-t border-neutral-100 font-mono text-xs text-neutral-700">
                                <td class="px-4 py-3">CNAME</td>
                                <td class="px-4 py-3" x-text="added.split('.')[0]"></td>
                                <td class="px-4 py-3">{{ $target }}</td>
                                <td class="px-4 py-3">Auto</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="mt-3 flex items-center gap-3">
                    <x-ui.button icon="check">{{ __('Verify domain') }}</x-ui.button>
                    <p class="text-xs text-neutral-400">{{ __('DNS changes can take a few minutes to a few hours.') }}</p>
                </div>
            </x-ui.card>
        </template>

        {{-- Connected domains (each mapped to one product) --}}
        <div class="space-y-3">
            @foreach ($domains as $d)
                <x-ui.card>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-o-globe-alt class="h-5 w-5" /></span>
                            <div>
                                <p class="text-sm font-semibold text-neutral-900">{{ $d['host'] }}</p>
                                <p class="mt-0.5 flex items-center gap-1.5 text-xs text-neutral-500">
                                    <x-heroicon-o-arrow-turn-down-right class="h-3.5 w-3.5 text-neutral-300" />
                                    {{ __('Serves') }} <span class="font-medium text-neutral-700">{{ $d['page'] }}</span>
                                </p>
                                <div class="mt-1.5 flex items-center gap-2">
                                    <x-ui.badge :color="$d['color']" dot>{{ $d['status'] }}</x-ui.badge>
                                    @if ($d['ssl'])
                                        <span class="inline-flex items-center gap-1 text-xs text-emerald-600"><x-heroicon-s-lock-closed class="h-3 w-3" /> {{ __('SSL active') }}</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 text-xs text-neutral-400"><x-heroicon-o-lock-open class="h-3 w-3" /> {{ __('SSL pending') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('funnel.sales', ['slug' => $d['slug']]) }}" target="_blank" class="rounded-lg p-2 text-neutral-400 transition hover:bg-neutral-100 hover:text-neutral-600" title="{{ __('Open') }}"><x-heroicon-o-arrow-top-right-on-square class="h-4 w-4" /></a>
                            <button type="button" class="rounded-lg p-2 text-neutral-400 transition hover:bg-rose-50 hover:text-rose-600"><x-heroicon-o-trash class="h-4 w-4" /></button>
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </div>

        <x-ui.alert type="info" :title="__('How it works')">
            {{ __('Each sales page can have one custom domain. Point a CNAME at') }} <span class="font-mono">{{ $target }}</span>; {{ __('we verify ownership, issue free SSL, and route that domain straight to the sales page.') }}
        </x-ui.alert>
    </div>
</x-layouts.app>
