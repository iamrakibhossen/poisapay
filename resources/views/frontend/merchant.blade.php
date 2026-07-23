<x-layouts.app :title="__('Merchant')">
    <div x-data="{
            showEditProfile: {{ $isMerchant && $errors->hasAny(['businessName', 'category', 'website', 'supportEmail', 'settlementAssetId']) ? 'true' : 'false' }},
            confirmingCancel: null,
            confirmingRefund: null,
            qr: null,
            copied: false,
            copyLink() {
                if (! this.qr) return;
                navigator.clipboard.writeText(this.qr.payUrl).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); });
            },
        }" class="space-y-6">
        {{-- ============================= ONBOARDING ============================= --}}
        @unless ($isMerchant)
            <x-ui.page-header :title="__('Merchant')" :subtitle="__('Accept crypto payments with shareable, QR-ready invoices.')" />
            @unless ($featureEnabled)
                <x-ui.alert type="warning" :title="__('Merchant accounts unavailable')">
                    {{ __('Merchant onboarding is temporarily disabled. Please check back soon.') }}
                </x-ui.alert>
            @endunless

            @if ($featureEnabled && ! $isFullKyc)
                <x-ui.alert type="warning" :title="__('Full verification required')">
                    {{ __('Merchant accounts are only available to fully verified accounts. Complete identity verification to start accepting payments.') }}
                </x-ui.alert>
                <x-ui.card>
                    <x-ui.empty-state icon="building-storefront" :title="__('Become a merchant')"
                        :description="__('Once you\'re fully verified, you can create a merchant profile and start invoicing customers instantly.')">
                        <x-slot:action>
                            <a href="{{ route('settings.index', ['tab' => 'verification']) }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">
                                <x-heroicon-o-identification class="h-4 w-4" /> {{ __('Complete verification') }}
                            </a>
                        </x-slot:action>
                    </x-ui.empty-state>
                </x-ui.card>
            @endif

            {{-- Hero + registration form --}}
            @if ($canRegister)
                <div class="grid gap-6 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <div class="pp-card relative overflow-hidden bg-gradient-to-br from-brand-500 via-brand-600 to-brand-800 p-6 text-white shadow-[var(--shadow-card)]">
                            <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 85% 10%, white 1px, transparent 1px); background-size: 26px 26px;"></div>
                            <div class="relative">
                                <span class="inline-grid h-11 w-11 place-items-center rounded-xl bg-white/15">
                                    <x-heroicon-o-building-storefront class="h-6 w-6" />
                                </span>
                                <h2 class="mt-4 text-xl font-bold">{{ __('Become a merchant') }}</h2>
                                <p class="mt-2 text-sm text-white/80">
                                    {{ __('Turn your PoisaPay account into a payment gateway. Issue crypto invoices, share QR codes, and settle to your chosen asset.') }}
                                </p>
                                <ul class="mt-4 space-y-2 text-sm">
                                    <li class="flex items-center gap-2"><x-heroicon-o-check-circle class="h-4 w-4" /> {{ __('Shareable, QR-ready invoices') }}</li>
                                    <li class="flex items-center gap-2"><x-heroicon-o-check-circle class="h-4 w-4" /> {{ __('Instant crypto settlement') }}</li>
                                    <li class="flex items-center gap-2"><x-heroicon-o-check-circle class="h-4 w-4" /> {{ __('One-click refunds') }}</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <x-ui.card :title="__('Register your business')" :subtitle="__('Tell us a little about what you sell.')">
                            <form method="POST" action="{{ route('merchant.register') }}" class="space-y-5">
                                @csrf
                                <x-ui.input :label="__('Business name')" name="businessName" icon="building-storefront"
                                    :value="old('businessName')" :placeholder="__('Acme Coffee Co.')" :error="$errors->first('businessName')" />

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-ui.input :label="__('Category (optional)')" name="category" icon="tag"
                                        :value="old('category')" :placeholder="__('e.g. Retail, SaaS')" :error="$errors->first('category')" />
                                    <div>
                                        <x-ui.select :label="__('Settlement asset')" name="settlementAssetId" id="regSettlementAssetId" :error="$errors->first('settlementAssetId')">
                                            <option value="">{{ __('Platform default') }}</option>
                                            @foreach ($assets as $a)
                                                <option value="{{ $a->id }}" @selected(old('settlementAssetId') == $a->id)>{{ $a->symbol }} — {{ $a->name }}</option>
                                            @endforeach
                                        </x-ui.select>
                                    </div>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-ui.input :label="__('Website (optional)')" name="website" icon="globe-alt"
                                        :value="old('website')" placeholder="https://example.com" :error="$errors->first('website')" />
                                    <x-ui.input :label="__('Support email (optional)')" name="supportEmail" icon="envelope"
                                        :value="old('supportEmail')" placeholder="support@example.com" :error="$errors->first('supportEmail')" />
                                </div>

                                <x-ui.button type="submit" class="w-full" icon="building-storefront">{{ __('Create merchant profile') }}</x-ui.button>
                            </form>
                        </x-ui.card>
                    </div>
                </div>
            @endif
        @else
            {{-- ============================= DASHBOARD ============================= --}}
            {{-- Business identity header --}}
            @php
                $statusChip = [
                    'success' => 'bg-emerald-50 text-emerald-700',
                    'warning' => 'bg-amber-50 text-amber-700',
                    'danger' => 'bg-red-50 text-red-700',
                ][$merchant->status->color()] ?? 'bg-neutral-100 text-neutral-600';
            @endphp
            <div class="pp-card flex flex-col gap-4 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex min-w-0 items-center gap-4">
                    <span class="grid h-14 w-14 shrink-0 place-items-center rounded-2xl bg-gradient-to-br from-brand-500 to-brand-700 text-xl font-bold text-white shadow-sm">
                        {{ mb_strtoupper(mb_substr($merchant->business_name, 0, 1)) ?: 'M' }}
                    </span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="truncate text-lg font-bold tracking-tight text-neutral-900">{{ $merchant->business_name }}</h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusChip }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>{{ $merchant->status->label() }}
                            </span>
                        </div>
                        <p class="mt-0.5 truncate text-sm text-neutral-500">
                            {{ $merchant->category ?: __('Merchant account') }} · <span class="font-mono">{{ $merchant->slug }}</span> · {{ $feePct }} {{ __('fee') }}
                        </p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <x-ui.button x-on:click="showEditProfile = true" variant="secondary" size="sm" icon="pencil-square">{{ __('Edit profile') }}</x-ui.button>
                </div>
            </div>

            @if ($merchant->status === \App\Enums\MerchantStatus::Pending)
                <x-ui.alert type="info" :title="__('Application under review')">
                    {{ __("Your merchant profile is pending operator approval. You can set things up now — invoicing unlocks once you're approved.") }}
                </x-ui.alert>
            @endif
            @if ($merchant->status === \App\Enums\MerchantStatus::Suspended)
                <x-ui.alert type="danger" :title="__('Merchant account suspended')">
                    {{ $merchant->suspension_reason ?: __('Your merchant account has been suspended. Contact support for details.') }}
                </x-ui.alert>
            @endif

            {{-- Stat tiles --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="pp-card p-5">
                    <div class="flex items-center justify-between">
                        <span class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ __('Net revenue') }}</span>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-500">
                            <x-heroicon-o-banknotes class="h-5 w-5" />
                        </span>
                    </div>
                    <div class="mt-2 space-y-0.5">
                        @if ($stats['netRevenue']->isEmpty())
                            <p class="tabular text-2xl font-bold tracking-tight text-neutral-800">—</p>
                        @else
                            @foreach ($stats['netRevenue'] as $v)
                                <p class="tabular text-lg font-bold tracking-tight text-neutral-800">{{ $v }}</p>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="pp-card p-5">
                    <div class="flex items-center justify-between">
                        <span class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ __('Fees paid') }}</span>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-amber-100 text-amber-500">
                            <x-heroicon-o-receipt-percent class="h-5 w-5" />
                        </span>
                    </div>
                    <div class="mt-2 space-y-0.5">
                        @if ($stats['totalFees']->isEmpty())
                            <p class="tabular text-2xl font-bold tracking-tight text-neutral-800">—</p>
                        @else
                            @foreach ($stats['totalFees'] as $v)
                                <p class="tabular text-lg font-bold tracking-tight text-neutral-800">{{ $v }}</p>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="pp-card p-5">
                    <div class="flex items-center justify-between">
                        <span class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ __('Total invoices') }}</span>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-100 text-brand-600">
                            <x-heroicon-o-document-text class="h-5 w-5" />
                        </span>
                    </div>
                    <p class="tabular mt-2 text-2xl font-bold tracking-tight text-neutral-800">{{ $stats['totalInvoices'] }}</p>
                </div>

                <div class="pp-card p-5">
                    <div class="flex items-center justify-between">
                        <span class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ __('Paid invoices') }}</span>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-500">
                            <x-heroicon-o-check-circle class="h-5 w-5" />
                        </span>
                    </div>
                    <p class="tabular mt-2 text-2xl font-bold tracking-tight text-neutral-800">{{ $stats['paidCount'] }}</p>
                </div>
            </div>

            {{-- Business profile --}}
            <x-ui.card :title="__('Business profile')">
                <dl class="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-gray-500">{{ __('Business name') }}</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $merchant->business_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Slug') }}</dt>
                        <dd class="mt-0.5 font-mono text-gray-900">{{ $merchant->slug }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Status') }}</dt>
                        <dd class="mt-0.5">
                            <span @class([
                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                                'bg-emerald-50 text-emerald-700' => $merchant->status->color() === 'success',
                                'bg-amber-50 text-amber-700' => $merchant->status->color() === 'warning',
                                'bg-red-50 text-red-700' => $merchant->status->color() === 'danger',
                            ])>
                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                {{ $merchant->status->label() }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Category') }}</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $merchant->category ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Statement descriptor') }}</dt>
                        <dd class="mt-0.5 font-mono text-gray-900">{{ $merchant->statement_descriptor ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Processing fee') }}</dt>
                        <dd class="mt-0.5 tabular font-medium text-gray-900">{{ $feePct }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Settlement asset') }}</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $merchant->settlementAsset?->symbol ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Support email') }}</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $merchant->support_email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('Website') }}</dt>
                        <dd class="mt-0.5 truncate font-medium text-gray-900">
                            @if ($merchant->website)
                                <a href="{{ $merchant->website }}" target="_blank" rel="noopener" class="text-brand-600 hover:underline">{{ $merchant->website }}</a>
                            @else
                                <span>—</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-ui.card>

            <div class="grid gap-6 lg:grid-cols-5">
                {{-- Create invoice form --}}
                <div class="lg:col-span-2">
                    <x-ui.card :title="__('Create invoice')" :subtitle="__('Generate a payment request for a customer.')">
                        @if ($merchant->status !== \App\Enums\MerchantStatus::Active)
                            <x-ui.empty-state icon="clock" :title="__('Invoicing locked')"
                                :description="__('You\'ll be able to create invoices once your merchant account is active.')" />
                        @elseif ($assets->isEmpty())
                            <x-ui.empty-state icon="cube" :title="__('No crypto assets')"
                                :description="__('Crypto assets must be configured before you can invoice.')" />
                        @else
                            <form method="POST" action="{{ route('merchant.invoice.create') }}" class="space-y-5">
                                @csrf
                                <div>
                                    <x-ui.select :label="__('Asset')" name="assetId" :error="$errors->first('assetId')">
                                        @foreach ($assets as $a)
                                            <option value="{{ $a->id }}" @selected(old('assetId') == $a->id)>{{ $a->symbol }} — {{ $a->name }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>

                                <x-ui.input :label="__('Amount')" name="amount" type="text" inputmode="decimal"
                                    :value="old('amount')" placeholder="0.00" :error="$errors->first('amount')" />

                                <x-ui.input :label="__('Reference (optional)')" name="reference" icon="hashtag"
                                    :value="old('reference')" :placeholder="__('Order #1234')" :error="$errors->first('reference')"
                                    :hint="__('Leave blank to auto-generate a unique reference.')" />

                                <x-ui.input :label="__('Memo (optional)')" name="memo" icon="chat-bubble-left-ellipsis"
                                    :value="old('memo')" :placeholder="__('What\'s this for?')" :error="$errors->first('memo')" />

                                <x-ui.button type="submit" class="w-full" icon="plus">{{ __('Create invoice') }}</x-ui.button>
                            </form>
                        @endif
                    </x-ui.card>
                </div>

                {{-- Invoices table --}}
                <div class="lg:col-span-3">
                    <x-ui.card :title="__('Your invoices')" :subtitle="__('Your 50 most recent invoices.')" padding="p-5 sm:p-6">
                        @if ($invoices->isNotEmpty())
                            <x-ui.table :headers="[__('Reference'), __('Amount'), __('Fee'), __('Net'), __('Status'), __('Payer'), __('Created'), '']">
                                @foreach ($invoices as $invoice)
                                    <tr class="border-b border-gray-200 hover:bg-gray-100">
                                        <td class="px-3 py-3">
                                            <p class="font-mono text-xs text-neutral-700">{{ $invoice['reference'] }}</p>
                                            @if ($invoice['memo'])
                                                <p class="max-w-[10rem] truncate text-xs text-neutral-400">{{ $invoice['memo'] }}</p>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3">
                                            <span class="tabular text-sm font-semibold text-neutral-900">{{ $invoice['amount'] }}</span>
                                        </td>
                                        <td class="px-3 py-3 tabular text-sm text-neutral-500">{{ $invoice['fee'] }}</td>
                                        <td class="px-3 py-3 tabular text-sm font-medium text-neutral-800">{{ $invoice['net'] }}</td>
                                        <td class="px-3 py-3">
                                            <span @class([
                                                'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
                                                'bg-emerald-50 text-emerald-700' => $invoice['statusColor'] === 'success',
                                                'bg-amber-50 text-amber-700' => $invoice['statusColor'] === 'warning',
                                                'bg-sky-50 text-sky-700' => $invoice['statusColor'] === 'info',
                                                'bg-red-50 text-red-700' => $invoice['statusColor'] === 'danger',
                                                'bg-neutral-100 text-neutral-600' => $invoice['statusColor'] === 'gray',
                                            ])>
                                                <span class="h-1.5 w-1.5 rounded-full bg-current"></span>
                                                {{ $invoice['statusLabel'] }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-3 text-sm text-neutral-500">{{ $invoice['payer'] ?: '—' }}</td>
                                        <td class="px-3 py-3 whitespace-nowrap text-sm text-neutral-500">{{ $invoice['created'] }}</td>
                                        <td class="px-3 py-3 text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                @if ($invoice['isPayable'])
                                                    <x-ui.button x-on:click="qr = {{ \Illuminate\Support\Js::from(['payUrl' => $invoice['payUrl'], 'amount' => $invoice['amount'], 'reference' => $invoice['reference'], 'qrSvg' => $invoice['qrSvg']]) }}; copied = false" variant="secondary" size="sm" icon="qr-code">{{ __('QR / Link') }}</x-ui.button>
                                                    <x-ui.button x-on:click="confirmingCancel = {{ \Illuminate\Support\Js::from(['id' => $invoice['id'], 'reference' => $invoice['reference']]) }}" variant="ghost" size="sm" icon="x-circle">{{ __('Cancel') }}</x-ui.button>
                                                @elseif ($invoice['status'] === 'paid' && $allowRefunds)
                                                    <x-ui.button x-on:click="confirmingRefund = {{ \Illuminate\Support\Js::from(['id' => $invoice['id'], 'reference' => $invoice['reference']]) }}" variant="ghost" size="sm" icon="arrow-uturn-left">{{ __('Refund') }}</x-ui.button>
                                                @else
                                                    <span class="text-xs text-neutral-400">—</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </x-ui.table>
                        @else
                            <x-ui.empty-state icon="document-text" :title="__('No invoices yet')"
                                :description="__('Create your first invoice to start accepting payments.')" />
                        @endif
                    </x-ui.card>
                </div>
            </div>
        @endunless

        {{-- ============================= MODALS ============================= --}}

        @if ($isMerchant)
            {{-- Edit profile modal --}}
            <div x-show="showEditProfile" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="showEditProfile = false"></div>
                <div class="relative m-4 w-full max-w-lg overflow-hidden rounded-lg bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Edit business profile') }}</h3>
                        <button type="button" x-on:click="showEditProfile = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="{{ __('Close') }}">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                    <form method="POST" action="{{ route('merchant.profile') }}" class="space-y-4">
                        @csrf @method('PUT')
                        <x-ui.input :label="__('Business name')" name="businessName" icon="building-storefront"
                            :value="old('businessName', $merchant->business_name)" :error="$errors->first('businessName')" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input :label="__('Category')" name="category" icon="tag"
                                :value="old('category', $merchant->category)" :error="$errors->first('category')" />
                            <div>
                                <x-ui.select :label="__('Settlement asset')" name="settlementAssetId" id="editSettlementAssetId" :error="$errors->first('settlementAssetId')">
                                    <option value="">{{ __('Platform default') }}</option>
                                    @foreach ($assets as $a)
                                        <option value="{{ $a->id }}" @selected(old('settlementAssetId', $merchant->settlement_asset_id) == $a->id)>{{ $a->symbol }} — {{ $a->name }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                        </div>
                        <x-ui.input :label="__('Website')" name="website" icon="globe-alt"
                            :value="old('website', $merchant->website)" placeholder="https://example.com" :error="$errors->first('website')" />
                        <x-ui.input :label="__('Support email')" name="supportEmail" icon="envelope"
                            :value="old('supportEmail', $merchant->support_email)" placeholder="support@example.com" :error="$errors->first('supportEmail')" />
                        <div class="flex justify-end gap-2 pt-1">
                            <x-ui.button type="button" variant="secondary" x-on:click="showEditProfile = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="submit" icon="check">{{ __('Save changes') }}</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Cancel confirmation --}}
            <div x-show="confirmingCancel" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="confirmingCancel = null"></div>
                <div class="relative m-4 w-full max-w-md overflow-hidden rounded-lg bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                            <x-heroicon-o-exclamation-triangle class="h-6 w-6 text-red-600" />
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Cancel this invoice?') }}</h3>
                            <p class="mt-2 text-sm text-gray-500">{{ __('The invoice will no longer be payable. This cannot be undone.') }}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="confirmingCancel = null">{{ __('Keep invoice') }}</x-ui.button>
                        <form method="POST" x-bind:action="confirmingCancel ? '{{ url('/merchant/invoices') }}/' + confirmingCancel.id + '/cancel' : ''">
                            @csrf
                            <x-ui.button type="submit" variant="danger" icon="x-circle">{{ __('Cancel invoice') }}</x-ui.button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Refund confirmation --}}
            <div x-show="confirmingRefund" x-cloak class="fixed inset-0 z-50 flex items-end justify-center sm:items-center">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="confirmingRefund = null"></div>
                <div class="relative m-4 w-full max-w-md overflow-hidden rounded-lg bg-white p-6 shadow-xl" role="dialog" aria-modal="true">
                    <div class="sm:flex sm:items-start">
                        <div class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-amber-100 sm:mx-0 sm:h-10 sm:w-10">
                            <x-heroicon-o-arrow-uturn-left class="h-6 w-6 text-amber-600" />
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                            <h3 class="text-lg font-semibold text-gray-900">{{ __('Refund this invoice?') }}</h3>
                            <p class="mt-2 text-sm text-gray-500">{{ __('The payer is made whole for the full amount. Your net and the processing fee are returned. This requires sufficient balance.') }}</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="confirmingRefund = null">{{ __('Keep payment') }}</x-ui.button>
                        <form method="POST" x-bind:action="confirmingRefund ? '{{ url('/merchant/invoices') }}/' + confirmingRefund.id + '/refund' : ''">
                            @csrf
                            <x-ui.button type="submit" icon="arrow-uturn-left">{{ __('Refund in full') }}</x-ui.button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- QR modal --}}
            <div x-show="qr" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="qr = null"></div>
                <div class="relative w-full max-w-md pp-card p-6" role="dialog" aria-modal="true">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">{{ __('Scan to pay') }}</h3>
                        <button type="button" x-on:click="qr = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>

                    <div class="flex flex-col items-center gap-4">
                        <div class="rounded-xl border border-neutral-200 bg-white p-3" x-html="qr?.qrSvg"></div>
                        <div class="text-center">
                            <p class="tabular text-lg font-semibold text-neutral-900" x-text="qr?.amount"></p>
                            <p class="font-mono text-xs text-neutral-500" x-text="qr?.reference"></p>
                        </div>
                        <div class="w-full">
                            <label class="pp-label">{{ __('Payment link') }}</label>
                            <div class="flex items-stretch gap-2">
                                <code class="min-w-0 flex-1 break-all rounded-xl border border-neutral-200 bg-neutral-50 px-3 py-2.5 font-mono text-xs text-neutral-800" x-text="qr?.payUrl"></code>
                                <button type="button" x-on:click="copyLink()"
                                    class="inline-flex shrink-0 items-center gap-1.5 rounded-xl border border-neutral-200 px-3 text-sm font-medium text-neutral-700 hover:bg-neutral-50">
                                    <x-heroicon-o-clipboard-document x-show="!copied" class="h-4 w-4" />
                                    <x-heroicon-o-check x-show="copied" x-cloak class="h-4 w-4 text-emerald-500" />
                                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-layouts.app>
