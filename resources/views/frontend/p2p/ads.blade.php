<x-layouts.app :title="'My P2P ads'">
    <div class="space-y-6">
        <x-ui.page-header title="My ads" subtitle="Manage your P2P advertisements.">
            <x-slot:actions>
                <a href="{{ route('p2p') }}"><x-ui.button variant="secondary" icon="arrow-left">Marketplace</x-ui.button></a>
                <a href="{{ route('p2p.payment-methods') }}"><x-ui.button variant="secondary" icon="credit-card">Payment accounts</x-ui.button></a>
                <a href="{{ route('p2p.ads.create') }}"><x-ui.button icon="plus">Post ad</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif

        {{-- My reputation + availability --}}
        @php $rep = app(\App\Domain\P2p\P2pReputationService::class); @endphp
        <x-ui.card>
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.badge color="brand">{{ $rep->levelLabel((int) $profile->level) }}</x-ui.badge>
                    <span class="text-sm text-neutral-600">{{ number_format($profile->completion_rate_bps / 100, 1) }}% completion · {{ number_format($profile->trade_count) }} trades</span>
                    @foreach ($profile->badges ?? [] as $b)<x-ui.badge color="info">{{ $rep->badgeLabel($b) }}</x-ui.badge>@endforeach
                    <a href="{{ route('p2p.merchant', $profile->user_id) }}" class="text-sm font-medium text-brand-600 hover:underline">View public profile</a>
                </div>
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('p2p.merchant.online') }}">@csrf
                        <x-ui.button type="submit" size="sm" :variant="$profile->is_online ? 'success' : 'secondary'">
                            {{ $profile->is_online ? '● Online' : '○ Offline' }}
                        </x-ui.button>
                    </form>
                    <form method="POST" action="{{ route('p2p.merchant.vacation') }}">@csrf
                        <x-ui.button type="submit" size="sm" :variant="$profile->vacation_mode ? 'primary' : 'secondary'">
                            {{ $profile->vacation_mode ? 'Vacation: on' : 'Vacation: off' }}
                        </x-ui.button>
                    </form>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <x-ui.table :headers="['Type', 'Price', 'Available', 'Limits', 'Status', '']">
                @forelse ($ads as $ad)
                    <tr class="border-b border-neutral-100">
                        <td class="px-3 py-3"><x-ui.badge :color="$ad->side->color()">{{ $ad->side->label() }}</x-ui.badge></td>
                        <td class="px-3 py-3 text-sm text-neutral-900 tabular">
                            {{ $ad->price_type->value === 'fixed' ? number_format((float) $ad->fixed_price, 2).' '.$ad->fiat_currency : 'Floating' }}
                        </td>
                        <td class="px-3 py-3 text-sm text-neutral-600 tabular">{{ $ad->availableMoney()->format() }}</td>
                        <td class="px-3 py-3 text-sm text-neutral-500 tabular">{{ number_format((float) $ad->min_order, 0) }}–{{ number_format((float) $ad->max_order, 0) }}</td>
                        <td class="px-3 py-3"><x-ui.badge :color="$ad->status->color()" dot>{{ $ad->status->label() }}</x-ui.badge></td>
                        <td class="px-3 py-3">
                            @if (in_array($ad->status->value, ['active', 'paused']))
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('p2p.ads.edit', $ad) }}"><x-ui.button size="sm" variant="secondary" icon="pencil-square">Edit</x-ui.button></a>
                                    <form method="POST" action="{{ route('p2p.ads.toggle', $ad) }}" class="inline">
                                        @csrf
                                        <x-ui.button type="submit" size="sm" variant="secondary">{{ $ad->status->value === 'active' ? 'Pause' : 'Resume' }}</x-ui.button>
                                    </form>
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-ui.empty-state icon="megaphone" title="No ads" description="Post your first ad to start trading." /></td></tr>
                @endforelse
            </x-ui.table>
            <div class="mt-4">{{ $ads->links() }}</div>
        </x-ui.card>
    </div>
</x-layouts.app>
