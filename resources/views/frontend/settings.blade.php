<x-layouts.app :title="__('Settings')">
    @php
        $user = auth()->user();
        $twoFactorSetup = session('twoFactorSetup');
        $nav = [
            'profile' => ['label' => __('Profile'), 'icon' => 'user-circle', 'hint' => __('Name, contact & preferences')],
            'security' => ['label' => __('Security'), 'icon' => 'shield-check', 'hint' => __('2FA, phone & addresses')],
            'password' => ['label' => __('Password'), 'icon' => 'key', 'hint' => __('Change your password')],
            'verification' => ['label' => __('Verification'), 'icon' => 'identification', 'hint' => __('Identity & KYC')],
            'devices' => ['label' => __('Devices'), 'icon' => 'computer-desktop', 'hint' => __('Signed-in devices')],
            'preferences' => ['label' => __('Preferences'), 'icon' => 'adjustments-horizontal', 'hint' => __('Spending priority')],
            'sessions' => ['label' => __('Sessions'), 'icon' => 'globe-alt', 'hint' => __('Active sessions')],
        ];
        $current = $nav[$activeTab];
        // Each section is its own URL (/settings/{tab}); a full page, not a client tab.
        $sectionClass = in_array($activeTab, ['profile', 'security', 'password', 'verification'], true) ? 'space-y-8' : '';
    @endphp

    <div class="mx-auto max-w-5xl">
        <header class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Settings') }}</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ __('Manage your profile, security and preferences.') }}</p>
        </header>

        <div class="grid gap-8 lg:grid-cols-4">
            {{-- Side navigation — real links to each settings sub-page. --}}
            <nav class="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 lg:sticky lg:top-6 lg:col-span-1 lg:mx-0 lg:flex-col lg:self-start lg:overflow-visible lg:px-0 lg:pb-0">
                @foreach ($nav as $key => $item)
                    <a href="{{ route('settings.index', ['tab' => $key]) }}" @class([
                        'group flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm transition lg:w-full',
                        'bg-neutral-900 font-semibold text-white shadow-sm' => $activeTab === $key,
                        'font-medium text-neutral-600 hover:bg-neutral-100 hover:text-neutral-900' => $activeTab !== $key,
                    ])>
                        <x-dynamic-component :component="'heroicon-o-'.$item['icon']" @class([
                            'h-5 w-5 shrink-0',
                            'text-white' => $activeTab === $key,
                            'text-neutral-400 group-hover:text-neutral-600' => $activeTab !== $key,
                        ]) />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="lg:col-span-3">
                {{-- Section heading --}}
                <div class="mb-6 flex items-center gap-3 border-b border-neutral-200 pb-4">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-brand-50 text-brand-600">
                        <x-dynamic-component :component="'heroicon-o-'.$current['icon']" class="h-5 w-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-neutral-900">{{ $current['label'] }}</h2>
                        <p class="text-xs text-neutral-500">{{ $current['hint'] }}</p>
                    </div>
                </div>

                {{-- The active section's body lives in its own partial (frontend/partials/settings-*). --}}
                <div class="{{ $sectionClass }}">
                    @include('frontend.partials.settings-'.$activeTab)
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
