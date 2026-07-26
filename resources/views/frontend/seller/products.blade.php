<x-layouts.app :title="__('Products')">
    <div class="mt-6 space-y-5" x-data="{ del: { url: '', name: '' } }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Products') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Each product gets its own sales page you can share in ads.') }}</p>
            </div>
            <x-ui.button href="{{ route('shop.products.create') }}" icon="plus">{{ __('Create product') }}</x-ui.button>
        </div>

        @if (session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert type="danger">{{ session('error') }}</x-ui.alert>
        @endif

        @if (count($products))
            <x-ui.history-table :columns="[
                ['label' => __('Product')],
                ['label' => __('Type')],
                ['label' => __('Status')],
                ['label' => __('Sales'), 'align' => 'right'],
                ['label' => __('Price'), 'align' => 'right'],
                ['label' => __('Page'), 'align' => 'right', 'sr' => true],
                ['label' => __('Actions'), 'align' => 'right', 'sr' => true],
            ]">
                @foreach ($products as $p)
                    <tr class="transition hover:bg-neutral-50/70">
                        <td class="px-5 py-4 align-middle">
                            <div class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-o-cube class="h-4 w-4" /></span>
                                <p class="text-sm font-medium text-neutral-900">{{ $p['name'] }}</p>
                            </div>
                        </td>
                        <td class="px-5 py-4 align-middle text-sm text-neutral-600">{{ $p['type'] }}</td>
                        <td class="px-5 py-4 align-middle"><x-ui.badge :color="$p['statusColor']" dot>{{ $p['status'] }}</x-ui.badge></td>
                        <td class="tabular px-5 py-4 text-right align-middle text-sm text-neutral-700">{{ number_format($p['sales']) }}</td>
                        <td class="tabular px-5 py-4 text-right align-middle text-sm font-semibold text-neutral-900">{{ $p['price'] }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                            @if (! empty($p['slug']))
                                <a href="{{ route('funnel.sales', ['slug' => $p['slug']]) }}" target="_blank" class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700">
                                    {{ __('View') }} <x-heroicon-o-arrow-top-right-on-square class="h-3.5 w-3.5" />
                                </a>
                            @else
                                <span class="text-xs text-neutral-400">{{ __('No page') }}</span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('shop.products.edit', $p['id']) }}" class="inline-flex items-center gap-1 text-xs font-semibold text-neutral-500 hover:text-brand-600">
                                    <x-heroicon-o-pencil-square class="h-3.5 w-3.5" /> {{ __('Edit') }}
                                </a>
                                @if ($p['canDelete'])
                                    <button type="button"
                                        x-on:click="del = { url: '{{ route('shop.products.delete', $p['id']) }}', name: @js($p['name']) }; $dispatch('open-modal', 'shop-product-delete')"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-neutral-400 hover:text-red-600">
                                        <x-heroicon-o-trash class="h-3.5 w-3.5" /> {{ __('Delete') }}
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs font-medium text-neutral-300" title="{{ __('Has orders — archive instead') }}">
                                        <x-heroicon-o-lock-closed class="h-3.5 w-3.5" /> {{ __('Delete') }}
                                    </span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.history-table>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="cube" :title="__('No products yet')" :description="__('Create your first product to get a shareable sales page.')">
                    <x-slot:action>
                        <x-ui.button href="{{ route('shop.products.create') }}" icon="plus" size="sm">{{ __('Create product') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @endif

        <x-ui.modal name="shop-product-delete" :title="__('Delete product')" maxWidth="sm">
            <p class="text-sm text-neutral-600">{{ __('Delete') }} <span class="font-medium text-neutral-900" x-text="del.name"></span>? {{ __('This can’t be undone.') }}</p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('Products with orders can’t be deleted — archive them instead.') }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <x-ui.button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'shop-product-delete')">{{ __('Cancel') }}</x-ui.button>
                <form method="POST" :action="del.url">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="danger" icon="trash">{{ __('Delete product') }}</x-ui.button>
                </form>
            </div>
        </x-ui.modal>
    </div>
</x-layouts.app>
