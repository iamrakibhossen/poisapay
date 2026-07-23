<x-layouts.admin :title="__('Payment method · :name', ['name' => $method->name])">
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            base: '{{ route('admin.p2p-payment-methods') }}',
            editingId: '{{ $method->id }}',
            form: {
                name: @js(old('name', $method->name)),
                key: @js(old('key', $method->key)),
                type: @js(old('type', $method->type)),
                country: @js(old('country', $method->country ?? '')),
                sort: @js(old('sort', (int) $method->sort)),
                is_active: {{ old('is_active', $method->is_active ? 1 : 0) ? 'true' : 'false' }},
            },
            fields: @js(old('fields', $method->fields ?? [])),
            get action() { return this.base + '/' + this.editingId; },
            addField() { this.fields.push({ key: '', label: '', required: false }); },
        }" class="space-y-6">

        {{-- Breadcrumb --}}
        <a href="{{ route('admin.p2p-payment-methods') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800">
            <x-heroicon-o-arrow-left class="h-4 w-4" /> {{ __('P2P Payment Methods') }}
        </a>

        <x-ui.page-header :title="$method->name" :subtitle="__('Rail configuration and the accounts users have saved on it.')">
            <x-slot:actions>
                <x-ui.button x-on:click="open = true" icon="pencil-square" size="sm" variant="secondary">{{ __('Edit') }}</x-ui.button>
                <form method="POST" action="{{ route('admin.p2p-payment-methods.delete', $method) }}">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" size="sm" variant="ghost" icon="trash">{{ __('Delete') }}</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif
        @if (session('error'))<x-ui.alert type="error">{{ session('error') }}</x-ui.alert>@endif

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Configuration --}}
            <x-ui.card :title="__('Configuration')" class="lg:col-span-2">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Key') }}</dt>
                        <dd class="mt-1 font-mono text-sm text-gray-900">{{ $method->key }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Type') }}</dt>
                        <dd class="mt-1"><x-ui.badge color="gray">{{ ucfirst($method->type) }}</x-ui.badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Country') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900">{{ $method->country ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Status') }}</dt>
                        <dd class="mt-1"><x-ui.badge :color="$method->is_active ? 'success' : 'gray'" dot>{{ $method->is_active ? __('Active') : __('Off') }}</x-ui.badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Sort') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900 tabular">{{ (int) $method->sort }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Saved accounts') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900 tabular">{{ number_format($method->user_accounts_count) }}</dd>
                    </div>
                </dl>
            </x-ui.card>

            {{-- Account field schema --}}
            <x-ui.card :title="__('Account fields')" :subtitle="__('What a user enters when saving an account.')">
                @php $fields = $method->fields ?? []; @endphp
                @if (count($fields))
                    <ul class="space-y-2">
                        @foreach ($fields as $f)
                            <li class="flex items-center justify-between gap-3 rounded-lg border border-gray-100 bg-gray-50/60 px-3 py-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-gray-900">{{ $f['label'] ?? $f['key'] ?? '—' }}</p>
                                    <p class="truncate font-mono text-xs text-gray-400">{{ $f['key'] ?? '' }}</p>
                                </div>
                                @if (! empty($f['required']))
                                    <x-ui.badge color="warning">{{ __('Required') }}</x-ui.badge>
                                @else
                                    <x-ui.badge color="gray">{{ __('Optional') }}</x-ui.badge>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-gray-400">{{ __('No fields defined.') }}</p>
                @endif
            </x-ui.card>
        </div>

        {{-- User accounts on this rail --}}
        <div>
            <div class="mb-3 flex items-center gap-2">
                <h2 class="text-sm font-semibold text-gray-900">{{ __('User accounts') }}</h2>
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500 tabular">{{ number_format($method->user_accounts_count) }}</span>
            </div>

            @if ($accounts->count())
                <x-ui.table :headers="[__('User'), __('Label'), __('Status'), __('Added')]">
                    @foreach ($accounts as $acct)
                        <tr class="border-b border-gray-100 last:border-0 hover:bg-gray-50">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-900">{{ $acct->user?->name ?? __('Unknown') }}</p>
                                <p class="text-xs text-gray-400">{{ $acct->user?->email }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $acct->label ?: '—' }}</td>
                            <td class="px-4 py-3"><x-ui.badge :color="$acct->is_active ? 'success' : 'gray'" dot>{{ $acct->is_active ? __('Active') : __('Off') }}</x-ui.badge></td>
                            <td class="px-4 py-3 text-gray-500">{{ $acct->created_at?->format('M j, Y') }}</td>
                        </tr>
                    @endforeach
                </x-ui.table>

                <p class="mt-3 flex items-center gap-1.5 text-xs text-gray-400">
                    <x-heroicon-o-lock-closed class="h-3.5 w-3.5" />
                    {{ __('Account details are encrypted and never shown here.') }}
                </p>

                @if ($accounts->hasPages())
                    <div class="mt-4">{{ $accounts->links() }}</div>
                @endif
            @else
                <x-ui.card>
                    <x-ui.empty-state icon="user-group" :title="__('No accounts yet')" :description="__('No users have saved a payout account on this rail.')" />
                </x-ui.card>
            @endif
        </div>

        @include('admin.partials.p2p-payment-method-form')
    </div>
</x-layouts.admin>
