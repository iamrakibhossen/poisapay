<x-layouts.app :title="__('Coupons')">
    <div class="mt-6 space-y-5" x-data="{ create: false }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Coupons') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Run discounts and limited-time campaigns.') }}</p>
            </div>
            <x-ui.button x-on:click="create = ! create" icon="plus">{{ __('New coupon') }}</x-ui.button>
        </div>

        {{-- Create form (inline) --}}
        <div x-show="create" x-cloak>
            <x-ui.card>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <x-ui.input :label="__('Code')" name="code" placeholder="LAUNCH20" />
                    <x-ui.select :label="__('Type')" name="type">
                        <option>{{ __('Percentage') }}</option>
                        <option>{{ __('Fixed amount') }}</option>
                    </x-ui.select>
                    <x-ui.input :label="__('Value')" name="value" placeholder="20" />
                    <x-ui.input :label="__('Usage limit')" name="limit" type="number" placeholder="500" />
                    <x-ui.input :label="__('Expires')" name="expires" type="date" />
                    <div class="flex items-end sm:col-span-2 lg:col-span-3">
                        <x-ui.button icon="check">{{ __('Create coupon') }}</x-ui.button>
                    </div>
                </div>
            </x-ui.card>
        </div>

        <x-ui.history-table :columns="[
            ['label' => __('Code')],
            ['label' => __('Discount')],
            ['label' => __('Status')],
            ['label' => __('Used'), 'align' => 'right'],
            ['label' => __('Expires'), 'align' => 'right'],
        ]">
            @foreach ($coupons as $c)
                <tr class="transition hover:bg-neutral-50/70">
                    <td class="px-5 py-4 align-middle"><code class="rounded-md bg-neutral-100 px-2 py-1 font-mono text-xs font-semibold text-neutral-700">{{ $c['code'] }}</code></td>
                    <td class="px-5 py-4 align-middle text-sm font-medium text-neutral-900">{{ $c['type'] }}</td>
                    <td class="px-5 py-4 align-middle"><x-ui.badge :color="$c['color']" dot>{{ $c['status'] }}</x-ui.badge></td>
                    <td class="tabular px-5 py-4 text-right align-middle text-sm text-neutral-700">{{ number_format($c['used']) }} / {{ number_format($c['limit']) }}</td>
                    <td class="whitespace-nowrap px-5 py-4 text-right align-middle text-xs text-neutral-400">{{ $c['expires'] }}</td>
                </tr>
            @endforeach
        </x-ui.history-table>
    </div>
</x-layouts.app>
