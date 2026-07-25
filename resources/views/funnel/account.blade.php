<x-layouts.sales :title="__('Continue checkout')" robots="noindex,nofollow">
    <div class="min-h-screen bg-neutral-50">
        <header class="border-b border-neutral-200 bg-white">
            <div class="mx-auto flex max-w-md items-center justify-between px-4 py-3.5">
                <span class="flex items-center gap-2 text-sm font-bold text-neutral-900">
                    @if ($seller->logoUrl())
                        <img src="{{ $seller->logoUrl() }}" alt="{{ $seller->displayName() }}" class="h-7 w-7 rounded-lg object-cover" />
                    @else
                        <span class="grid h-7 w-7 place-items-center rounded-lg bg-brand-500 text-[11px] font-bold text-white">{{ mb_strtoupper(mb_substr($seller->displayName(), 0, 1)) }}</span>
                    @endif
                    {{ $seller->displayName() }}
                </span>
                <span class="inline-flex items-center gap-1 text-xs font-medium text-emerald-600"><x-heroicon-s-lock-closed class="h-3.5 w-3.5" /> {{ __('Secure') }}</span>
            </div>
        </header>

        <main class="mx-auto max-w-md px-4 py-10"
            x-data="{ mode: '{{ old('mode', 'new') }}' }">
            <div class="text-center">
                <h1 class="text-2xl font-bold tracking-tight text-neutral-900">{{ __('Almost there') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Create your account to complete your purchase of') }} <span class="font-medium text-neutral-700">{{ $product->name }}</span>.</p>
            </div>

            {{-- Mode switch --}}
            <div class="mx-auto mt-6 flex max-w-xs rounded-xl border border-neutral-200 bg-white p-1 text-sm font-medium">
                <button type="button" x-on:click="mode = 'new'" :class="mode === 'new' ? 'bg-brand-500 text-white shadow-sm' : 'text-neutral-500'" class="flex-1 rounded-lg py-2 transition">{{ __('Create account') }}</button>
                <button type="button" x-on:click="mode = 'existing'" :class="mode === 'existing' ? 'bg-brand-500 text-white shadow-sm' : 'text-neutral-500'" class="flex-1 rounded-lg py-2 transition">{{ __('Sign in') }}</button>
            </div>

            <form method="POST" action="{{ route('funnel.account.submit', ['slug' => $slug]) }}" class="mt-6 space-y-3" x-data="{ loading: false }" x-on:submit="loading = true">
                @csrf
                <input type="hidden" name="mode" :value="mode" />

                <div x-show="mode === 'new'" x-cloak>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Full name') }}</label>
                    <input name="name" value="{{ old('name') }}" placeholder="{{ __('Your name') }}"
                        class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-base focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                    @error('name')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Email') }}</label>
                    <input name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="you@example.com" required
                        class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-base focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                    @error('email')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="mb-1 block text-xs font-medium text-neutral-500">{{ __('Password') }}</label>
                    <input name="password" type="password" autocomplete="current-password" placeholder="••••••••" required
                        class="w-full rounded-lg border border-neutral-200 px-3 py-2.5 text-base focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                    @error('password')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-[11px] text-neutral-400" x-show="mode === 'new'" x-cloak>{{ __('At least 8 characters.') }}</p>
                </div>

                <button type="submit" x-bind:disabled="loading"
                    class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-3.5 text-sm font-semibold text-white transition hover:bg-brand-700 disabled:opacity-60">
                    <span x-show="! loading" class="flex items-center gap-2">
                        <span x-text="mode === 'new' ? '{{ __('Create account & continue') }}' : '{{ __('Sign in & continue') }}'"></span>
                        <x-heroicon-o-arrow-right class="h-4 w-4" />
                    </span>
                    <span x-show="loading" x-cloak>{{ __('Please wait…') }}</span>
                </button>

                <div class="flex items-center justify-center gap-3 pt-1 text-[11px] text-neutral-400">
                    <span class="inline-flex items-center gap-1"><x-heroicon-o-shield-check class="h-3.5 w-3.5" /> {{ __('Bank-grade security') }}</span>
                    <span>·</span>
                    <span>{{ __('Your details are encrypted') }}</span>
                </div>
            </form>

            <a href="{{ route('funnel.sales', ['slug' => $slug]) }}" class="mt-6 block text-center text-xs font-medium text-neutral-400 hover:text-neutral-600">← {{ __('Back to the page') }}</a>
        </main>
    </div>
</x-layouts.sales>
