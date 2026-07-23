<x-layouts.admin :title="__('Card Provider Health')">
    <div class="space-y-6">
        <x-ui.page-header :title="__('Card Provider Health')" :subtitle="__('Live status of every configured card provider and the capabilities each one supports.')" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($health as $key => $status)
                <div class="pp-card p-5">
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-semibold text-neutral-900">{{ ucfirst($key) }}</p>
                            @if ($key === $default)
                                <span class="text-xs text-brand-600">{{ __('default provider') }}</span>
                            @endif
                        </div>
                        <x-ui.badge :color="$status->healthy ? 'success' : 'danger'" dot>{{ $status->healthy ? __('Healthy') : __('Down') }}</x-ui.badge>
                    </div>

                    <p class="mt-2 text-xs text-neutral-500">
                        @if ($status->healthy)
                            {{ __('Latency') }} {{ $status->latencyMs ?? 0 }}ms
                        @else
                            {{ \Illuminate\Support\Str::limit($status->message ?? __('Unavailable'), 80) }}
                        @endif
                    </p>

                    <div class="mt-3 flex flex-wrap gap-1">
                        @forelse ($capabilities[$key] ?? [] as $cap)
                            <span class="rounded bg-neutral-100 px-2 py-0.5 text-xs text-neutral-600">{{ str_replace('_', ' ', $cap) }}</span>
                        @empty
                            <span class="text-xs text-neutral-400">{{ __('No capabilities reported.') }}</span>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-500">{{ __('Programs → provider') }}</p>
            <x-ui.table :headers="[__('Program'), __('Slug'), __('Driver'), __('Network'), __('Status')]">
                @forelse ($providers as $provider)
                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                        <td class="px-3 py-3 text-sm font-medium text-neutral-800">{{ $provider->name }}</td>
                        <td class="px-3 py-3 font-mono text-xs text-neutral-400">{{ $provider->slug }}</td>
                        <td class="px-3 py-3"><x-ui.badge color="info">{{ $provider->driver?->label() }}</x-ui.badge></td>
                        <td class="px-3 py-3 text-sm text-neutral-600">{{ ucfirst($provider->network) }}</td>
                        <td class="px-3 py-3"><x-ui.badge :color="$provider->is_active ? 'success' : 'gray'" dot>{{ $provider->is_active ? __('Active') : __('Inactive') }}</x-ui.badge></td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-ui.empty-state icon="rectangle-stack" :title="__('No providers')" :description="__('No card providers are configured yet.')" /></td></tr>
                @endforelse
            </x-ui.table>
        </div>
    </div>
</x-layouts.admin>
