<x-layouts.app :title="__('Become a Seller')">
    <div class="mx-auto mt-6 max-w-5xl space-y-6">
        {{-- Header --}}
        <div>
            <a href="{{ route('sell') }}" class="mb-3 inline-flex items-center gap-1 text-xs font-semibold text-neutral-400 hover:text-neutral-600">
                <x-heroicon-o-arrow-left class="h-3.5 w-3.5" /> {{ __('Back to Sell') }}
            </a>
            <div class="flex items-center gap-3">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-50 text-brand-600">
                    <x-heroicon-o-rocket-launch class="h-5 w-5" />
                </span>
                <div>
                    <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Become a Seller') }}</h1>
                    <p class="mt-1 text-sm text-neutral-500">{{ __('Sell digital downloads, licenses, memberships and services — each product gets its own hosted sales page and checkout.') }}</p>
                </div>
            </div>
        </div>

        @if (session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif
        @error('apply')
            <x-ui.alert type="danger" :title="__('We couldn’t submit your application')">{{ $message }}</x-ui.alert>
        @enderror

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- ─────────────────────────  FORM  ───────────────────────── --}}
            <form method="POST" action="{{ route('sell.apply.submit') }}"
                x-data="{ selected: {{ Illuminate\Support\Js::from(old('categories', [])) }} }" class="space-y-6 lg:col-span-2">
                @csrf

                {{-- Section: profile --}}
                <x-ui.card>
                    <div class="mb-4 flex items-center gap-2">
                        <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500 text-[11px] font-bold text-white">1</span>
                        <div>
                            <h2 class="text-sm font-semibold text-neutral-900">{{ __('Your seller profile') }}</h2>
                            <p class="text-xs text-neutral-500">{{ __('This is how buyers and our review team see you.') }}</p>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input :label="__('Store / brand name')" name="brand_name" icon="sparkles" :value="old('brand_name')"
                            :hint="__('Shown to buyers; defaults to your account name if blank.')"
                            :error="$errors->first('brand_name')" placeholder="Rahim Studios" />
                        <x-ui.select :label="__('Country')" name="country" icon="map-pin" :error="$errors->first('country')">
                            @foreach ($countries as $code => $name)
                                <option value="{{ $code }}" @selected(old('country', $defaultCountry) === $code)>{{ $name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>
                    <div class="mt-4">
                        <x-ui.textarea :label="__('Seller bio')" name="bio" rows="3"
                            :hint="__('A short intro — what you make and who it is for.')" :error="$errors->first('bio')">{{ old('bio') }}</x-ui.textarea>
                    </div>
                    <div class="mt-4">
                        <x-ui.input :label="__('Website / portfolio (optional)')" name="website" icon="globe-alt" type="url"
                            :value="old('website')" :error="$errors->first('website')" placeholder="https://…" />
                    </div>
                </x-ui.card>

                {{-- Section: categories --}}
                <x-ui.card>
                    <div class="mb-4 flex items-center gap-2">
                        <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500 text-[11px] font-bold text-white">2</span>
                        <div>
                            <h2 class="text-sm font-semibold text-neutral-900">{{ __('What will you sell?') }}</h2>
                            <p class="text-xs text-neutral-500">{{ __('Pick all that apply — you can change this later.') }}</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($categories as $key => $label)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="categories[]" value="{{ $key }}" class="peer sr-only"
                                    x-model="selected" />
                                <span class="inline-flex items-center gap-1.5 rounded-full border px-3.5 py-2 text-sm font-medium transition
                                    border-neutral-200 text-neutral-600 hover:border-neutral-300
                                    peer-checked:border-brand-500 peer-checked:bg-brand-50 peer-checked:text-brand-700">
                                    <span class="grid h-4 w-4 place-items-center rounded-full border transition
                                        border-neutral-300 peer-checked:border-brand-500 peer-checked:bg-brand-500">
                                        <span x-show="selected.includes('{{ $key }}')" x-cloak class="text-[9px] font-bold text-white">✓</span>
                                    </span>
                                    {{ $label }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @error('categories')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror
                </x-ui.card>

                {{-- Section: payouts + verification --}}
                <x-ui.card>
                    <div class="mb-4 flex items-center gap-2">
                        <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500 text-[11px] font-bold text-white">3</span>
                        <div>
                            <h2 class="text-sm font-semibold text-neutral-900">{{ __('Payouts & verification') }}</h2>
                            <p class="text-xs text-neutral-500">{{ __('Where your earnings settle, and how we verify you.') }}</p>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.select :label="__('Settlement currency')" name="settlement_asset_id" icon="wallet" :error="$errors->first('settlement_asset_id')">
                            <option value="">{{ __('Choose currency') }}</option>
                            @foreach ($settlementAssets as $a)
                                <option value="{{ $a['id'] }}" @selected((string) old('settlement_asset_id') === (string) $a['id'])>{{ $a['symbol'] }} — {{ $a['name'] }}</option>
                            @endforeach
                        </x-ui.select>
                        <div class="rounded-xl border border-neutral-200 bg-neutral-50/60 p-3">
                            <p class="flex items-center gap-1.5 text-xs font-medium text-neutral-700">
                                <x-heroicon-o-identification class="h-4 w-4 text-brand-600" /> {{ __('Identity verification (KYC)') }}
                            </p>
                            <p class="mt-1 text-xs text-neutral-500">{{ __('Approval requires a verified identity. You can complete this after applying.') }}</p>
                            <a href="{{ route('settings.index', ['tab' => 'verification']) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700">
                                {{ __('Go to verification') }} <x-heroicon-s-chevron-right class="h-4 w-4" />
                            </a>
                        </div>
                    </div>
                </x-ui.card>

                {{-- Terms + submit --}}
                <x-ui.card>
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="terms" value="1" @checked(old('terms'))
                            class="mt-0.5 h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500" />
                        <span class="text-sm text-neutral-600">
                            {{ __('I agree to the') }} <a href="#" class="font-medium text-brand-600 hover:text-brand-700">{{ __('Seller Terms') }}</a>
                            {{ __('and confirm my products will comply with the platform policies.') }}
                        </span>
                    </label>
                    @error('terms')<p class="mt-2 text-xs text-rose-600">{{ $message }}</p>@enderror

                    <div class="mt-5 flex flex-wrap items-center justify-between gap-4">
                        <p class="inline-flex items-center gap-1.5 text-xs text-neutral-400">
                            <x-heroicon-o-clock class="h-4 w-4" /> {{ __('Reviewed within 1–2 business days.') }}
                        </p>
                        <x-ui.button type="submit" size="lg" icon="paper-airplane">{{ __('Submit application') }}</x-ui.button>
                    </div>
                </x-ui.card>
            </form>

            {{-- ─────────────────────────  ASIDE  ───────────────────────── --}}
            <aside class="space-y-5 lg:sticky lg:top-6 lg:self-start">
                {{-- How it works --}}
                <x-ui.card>
                    <h2 class="text-sm font-semibold text-neutral-900">{{ __('How it works') }}</h2>
                    <ol class="mt-4 space-y-4">
                        @foreach ([
                            [__('Apply & verify'), __('Tell us what you sell and verify your identity.')],
                            [__('Add a product'), __('Create a product and publish its sales page.')],
                            [__('Share & get paid'), __('Drop the link anywhere — payouts land in your wallet.')],
                        ] as $i => [$t, $d])
                            <li class="flex gap-3">
                                <div class="flex flex-col items-center">
                                    <span class="tabular grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-500 text-[11px] font-bold text-white">{{ $i + 1 }}</span>
                                    @unless ($loop->last)<span class="mt-1 w-px flex-1 bg-neutral-200"></span>@endunless
                                </div>
                                <div class="pb-1">
                                    <p class="text-sm font-medium text-neutral-900">{{ $t }}</p>
                                    <p class="text-xs text-neutral-500">{{ $d }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </x-ui.card>

                {{-- What you get --}}
                <x-ui.card>
                    <h2 class="text-sm font-semibold text-neutral-900">{{ __('What you get') }}</h2>
                    <ul class="mt-4 space-y-4">
                        @foreach ([
                            ['document-text', __('Hosted sales page'), __('Each product gets a shareable page + checkout — no website needed.')],
                            ['banknotes', __('Get paid your way'), __('Wallet, card, crypto, bank & mobile money at checkout.')],
                            ['bolt', __('Instant delivery'), __('Digital goods delivered on payment; earnings settle to your wallet.')],
                        ] as [$icon, $title, $desc])
                            <li class="flex gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                                    <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
                                </span>
                                <div>
                                    <p class="text-sm font-medium text-neutral-900">{{ $title }}</p>
                                    <p class="text-xs text-neutral-500">{{ $desc }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </x-ui.card>

                <p class="px-1 text-xs text-neutral-400">
                    {{ __('Approval needs a verified identity — ') }}<a href="{{ route('settings.index', ['tab' => 'verification']) }}" class="font-medium text-brand-600 hover:underline">{{ __('start verification') }}</a>{{ __(' any time.') }}
                </p>
            </aside>
        </div>
    </div>
</x-layouts.app>
