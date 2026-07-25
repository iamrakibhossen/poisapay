<x-layouts.app :title="__('Customers')">
    <div class="mt-6 space-y-5">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Customers') }}</h1>
            <p class="mt-1 text-sm text-neutral-500">{{ __('Everyone who has bought from you.') }}</p>
        </div>

        @if (count($customers))
            <x-ui.history-table :columns="[
                ['label' => __('Customer')],
                ['label' => __('Since')],
                ['label' => __('Orders'), 'align' => 'right'],
                ['label' => __('Total spent'), 'align' => 'right'],
            ]">
                @foreach ($customers as $c)
                    <tr class="transition hover:bg-neutral-50/70">
                        <td class="px-5 py-4 align-middle">
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-50 text-xs font-semibold text-brand-700">{{ \Illuminate\Support\Str::of($c['name'])->explode(' ')->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') ?: '?' }}</span>
                                <div>
                                    <p class="text-sm font-medium text-neutral-900">{{ $c['name'] }}</p>
                                    <p class="text-xs text-neutral-400">{{ $c['email'] }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-4 align-middle text-sm text-neutral-600">{{ $c['since'] }}</td>
                        <td class="tabular px-5 py-4 text-right align-middle text-sm text-neutral-700">{{ number_format($c['orders']) }}</td>
                        <td class="tabular px-5 py-4 text-right align-middle text-sm font-semibold text-neutral-900">{{ $c['spent'] }}</td>
                    </tr>
                @endforeach
            </x-ui.history-table>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="users" :title="__('No customers yet')" :description="__('When someone buys one of your products, they’ll appear here with their order history and total spend.')" />
            </div>
        @endif
    </div>
</x-layouts.app>
