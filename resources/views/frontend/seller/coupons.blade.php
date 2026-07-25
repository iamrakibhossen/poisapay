<x-layouts.app :title="__('Coupons')">
    <div class="mt-6 space-y-5" x-data="{ create: {{ $errors->any() ? 'true' : 'false' }}, type: '{{ old('type', 'percent') }}' }">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Coupons') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Run discounts and limited-time campaigns.') }}</p>
            </div>
            <x-ui.button x-on:click="create = ! create" icon="plus">{{ __('New coupon') }}</x-ui.button>
        </div>

        @if (session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif

        {{-- Create form (inline) --}}
        <div x-show="create" x-cloak>
            <form method="POST" action="{{ route('sell.coupons.store') }}">
                @csrf
                <x-ui.card>
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.input :label="__('Code')" name="code" :value="old('code')" :error="$errors->first('coupon') ?: $errors->first('code')" placeholder="LAUNCH20" required />
                        <x-ui.select :label="__('Type')" name="type" x-model="type">
                            <option value="percent">{{ __('Percentage') }}</option>
                            <option value="fixed">{{ __('Fixed amount') }}</option>
                        </x-ui.select>
                        <x-ui.input :label="__('Value')" name="value" :value="old('value')" :error="$errors->first('value')" type="number" step="0.01" x-bind:placeholder="type === 'percent' ? '20' : '15.00'" required />
                        <x-ui.select :label="__('Applies to')" name="product_id">
                            <option value="">{{ __('All products') }}</option>
                            @foreach ($products as $id => $pname)
                                <option value="{{ $id }}" @selected(old('product_id') === (string) $id)>{{ $pname }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input :label="__('Usage limit (optional)')" name="usage_limit" :value="old('usage_limit')" type="number" placeholder="500" />
                        <x-ui.input :label="__('Per-customer limit (optional)')" name="per_customer_limit" :value="old('per_customer_limit')" type="number" placeholder="1" />
                        <x-ui.input :label="__('Expires (optional)')" name="ends_at" :value="old('ends_at')" type="date" />
                        <div class="flex items-end">
                            <x-ui.button type="submit" icon="check">{{ __('Create coupon') }}</x-ui.button>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-neutral-400" x-show="type === 'percent'">{{ __('Percentage off the order (max 100).') }}</p>
                    <p class="mt-2 text-xs text-neutral-400" x-show="type === 'fixed'" x-cloak>{{ __('Fixed amount off, in the product’s currency.') }}</p>
                </x-ui.card>
            </form>
        </div>

        @if (count($coupons))
            <x-ui.history-table :columns="[
                ['label' => __('Code')],
                ['label' => __('Discount')],
                ['label' => __('Applies to')],
                ['label' => __('Status')],
                ['label' => __('Used'), 'align' => 'right'],
                ['label' => __('Expires'), 'align' => 'right'],
                ['label' => __('Actions'), 'align' => 'right', 'sr' => true],
            ]">
                @foreach ($coupons as $c)
                    <tr class="transition hover:bg-neutral-50/70">
                        <td class="px-5 py-4 align-middle"><code class="rounded-md bg-neutral-100 px-2 py-1 font-mono text-xs font-semibold text-neutral-700">{{ $c['code'] }}</code></td>
                        <td class="px-5 py-4 align-middle text-sm font-medium text-neutral-900">{{ $c['type'] }}</td>
                        <td class="px-5 py-4 align-middle text-sm text-neutral-600">{{ $c['scope'] }}</td>
                        <td class="px-5 py-4 align-middle"><x-ui.badge :color="$c['color']" dot>{{ $c['status'] }}</x-ui.badge></td>
                        <td class="tabular px-5 py-4 text-right align-middle text-sm text-neutral-700">{{ number_format($c['used']) }}{{ $c['limit'] !== null ? ' / '.number_format($c['limit']) : '' }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-right align-middle text-xs text-neutral-400">{{ $c['expires'] }}</td>
                        <td class="whitespace-nowrap px-5 py-4 text-right align-middle">
                            <div class="flex items-center justify-end gap-2">
                                <form method="POST" action="{{ route('sell.coupons.toggle', ['id' => $c['id']]) }}">
                                    @csrf
                                    <button type="submit" class="text-xs font-medium text-neutral-500 hover:text-brand-600">{{ $c['active'] ? __('Disable') : __('Enable') }}</button>
                                </form>
                                <form method="POST" action="{{ route('sell.coupons.destroy', ['id' => $c['id']]) }}" onsubmit="return confirm('{{ __('Delete this coupon?') }}')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-neutral-400 hover:text-rose-600">{{ __('Delete') }}</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </x-ui.history-table>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="ticket" :title="__('No coupons yet')" :description="__('Create a discount code to run a promotion.')">
                    <x-slot:action>
                        <x-ui.button x-on:click="create = true" icon="plus" size="sm">{{ __('New coupon') }}</x-ui.button>
                    </x-slot:action>
                </x-ui.empty-state>
            </div>
        @endif
    </div>
</x-layouts.app>
