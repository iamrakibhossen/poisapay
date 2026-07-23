<x-layouts.admin :title="__('Users')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Users')" :subtitle="__('Manage accounts, KYC tiers and freeze money-movement access.')" />

        <form method="GET" action="{{ route('admin.users') }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            {{-- Tier tabs (query-string filter) --}}
            <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
                @foreach ($tabs as $key => $count)
                    <a href="{{ route('admin.users', array_filter(['tier' => $key, 'search' => $search])) }}"
                        @class([
                            'flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium capitalize transition',
                            'bg-white text-neutral-900 shadow-sm' => $tier === $key,
                            'text-neutral-500 hover:text-neutral-800' => $tier !== $key,
                        ])>
                        {{ $key }}
                        <span class="rounded-full bg-neutral-200 px-1.5 text-xs">{{ $count }}</span>
                    </a>
                @endforeach
            </div>

            <input type="hidden" name="tier" value="{{ $tier }}" />
            <x-ui.input name="search" :value="$search" icon="magnifying-glass" placeholder="{{ __('Search name, email, phone, handle…') }}" class="w-full sm:w-72" />
        </form>

        <x-ui.table :headers="[__('User'), __('Phone'), __('KYC tier'), __('KYC status'), __('Joined'), '']">
            @forelse ($users as $user)
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$user->name" size="sm" />
                            <div class="min-w-0">
                                <p class="flex items-center gap-1.5 truncate text-sm font-medium text-neutral-900">
                                    {{ $user->name }}
                                    @if ($user->is_frozen)
                                        <x-heroicon-s-lock-closed class="h-3.5 w-3.5 text-rose-500" title="{{ __('Frozen') }}" />
                                    @endif
                                </p>
                                <p class="truncate text-xs text-neutral-500">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $user->phone ?? '—' }}</td>
                    <td class="px-4 py-3"><x-ui.badge :color="$user->kyc_tier->color()">{{ $user->kyc_tier->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3"><x-ui.badge :color="$user->kyc_status->color()" dot>{{ $user->kyc_status->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $user->created_at->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            @if (auth('admin')->user()?->can('freeze-users') || auth('admin')->user()?->hasRole('super-admin'))
                                <form method="POST" action="{{ route('admin.users.freeze', $user->id) }}"
                                    onsubmit="return confirm('{{ $user->is_frozen ? 'Unfreeze '.$user->name.' and restore money movement?' : 'Freeze '.$user->name.' and block money movement?' }}')">
                                    @csrf
                                    @if ($user->is_frozen)
                                        <x-ui.button type="submit" variant="ghost" size="sm" icon="lock-open">{{ __('Unfreeze') }}</x-ui.button>
                                    @else
                                        <x-ui.button type="submit" variant="ghost" size="sm" icon="lock-closed">{{ __('Freeze') }}</x-ui.button>
                                    @endif
                                </form>
                            @endif
                            <x-ui.button href="{{ route('admin.users.show', $user) }}" variant="secondary" size="sm" icon="eye">{{ __('View') }}</x-ui.button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state icon="users" :title="__('No users')" :description="__('No accounts match your filters.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $users->links() }}
    </div>
</x-layouts.admin>
