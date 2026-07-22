@php
    $sections = [
        'general' => ['label' => 'General', 'icon' => 'adjustments-vertical'],
        'branding' => ['label' => 'Branding', 'icon' => 'photo'],
        'auth' => ['label' => 'Authentication', 'icon' => 'lock-closed'],
        'deposit' => ['label' => 'Deposit', 'icon' => 'arrow-down-tray'],
        'withdrawal' => ['label' => 'Withdrawal', 'icon' => 'arrow-up-tray'],
        'transfer' => ['label' => 'Transfer', 'icon' => 'arrows-right-left'],
        'exchange' => ['label' => 'Exchange', 'icon' => 'arrow-path-rounded-square'],
        'cards' => ['label' => 'Cards', 'icon' => 'credit-card'],
        'merchant' => ['label' => 'Merchant', 'icon' => 'building-storefront'],
        'credit' => ['label' => 'Credit', 'icon' => 'scale'],
        'rewards' => ['label' => 'Rewards', 'icon' => 'gift'],
        'compliance' => ['label' => 'Compliance', 'icon' => 'shield-exclamation'],
        'localization' => ['label' => 'Localization', 'icon' => 'language'],
        'announcement' => ['label' => 'Announcement', 'icon' => 'megaphone'],
    ];
@endphp

<x-layouts.admin :title="'Settings'">
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
