@php
    $sections = [
        'general' => ['label' => __('General'), 'icon' => 'adjustments-vertical'],
        'branding' => ['label' => __('Branding'), 'icon' => 'photo'],
        'auth' => ['label' => __('Authentication'), 'icon' => 'lock-closed'],
        'deposit' => ['label' => __('Deposit'), 'icon' => 'arrow-down-tray'],
        'withdrawal' => ['label' => __('Withdrawal'), 'icon' => 'arrow-up-tray'],
        'transfer' => ['label' => __('Transfer'), 'icon' => 'arrows-right-left'],
        'exchange' => ['label' => __('Exchange'), 'icon' => 'arrow-path-rounded-square'],
        'cards' => ['label' => __('Cards'), 'icon' => 'credit-card'],
        'merchant' => ['label' => __('Merchant'), 'icon' => 'building-storefront'],
        'p2p' => ['label' => __('P2P'), 'icon' => 'user-group'],
        'rewards' => ['label' => __('Rewards'), 'icon' => 'gift'],
        'compliance' => ['label' => __('Compliance'), 'icon' => 'shield-exclamation'],
        'localization' => ['label' => __('Localization'), 'icon' => 'language'],
        'announcement' => ['label' => __('Announcement'), 'icon' => 'megaphone'],
    ];
@endphp

<x-layouts.admin :title="__('Settings')">
    {{-- Full-bleed split (DollarHub): cancel the main padding so the nav sidebar spans edge-to-edge. --}}
    <div class="-m-4 sm:-m-5 lg:-m-6">
        <div class="grid min-h-[calc(100vh-4rem)] lg:grid-cols-5">
            {{-- White bordered vertical section nav --}}
            <nav class="flex flex-1 flex-col gap-2 border-b border-gray-200 bg-white p-4 lg:col-span-1 lg:h-full lg:border-b-0 lg:border-r">
                @foreach ($sections as $key => $meta)
                    <x-admin.side-nav-link :icon="'heroicon-o-'.$meta['icon']" :active="$section === $key" :href="route('admin.settings', $key)">
                        {{ $meta['label'] }}
                    </x-admin.side-nav-link>
                @endforeach
            </nav>

            {{-- Content column with its own wrapper spacing --}}
            <div class="space-y-6 px-4 py-6 sm:px-6 lg:col-span-4 lg:px-8 lg:py-8">
                @include('admin.settings._'.$section)
            </div>
        </div>
    </div>
</x-layouts.admin>
