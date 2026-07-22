<x-layouts.app :title="'Merchant'">
    <div x-data="{
            showEditProfile: {{ $errors->any() && $isMerchant ? 'true' : 'false' }},
            confirmingCancel: null,
            confirmingRefund: null,
            qr: null,
            copied: false,
            copyLink() {
                if (! this.qr) return;
                navigator.clipboard.writeText(this.qr.payUrl).then(() => { this.copied = true; setTimeout(() => this.copied = false, 2000); });
            },
        }" class="space-y-6">
        <x-ui.page-header title="Merchant" subtitle="Accept crypto payments with shareable, QR-ready invoices." />

        {{-- ============================= ONBOARDING ============================= --}}
        @unless ($isMerchant)
            @unless ($featureEnabled)
                <x-ui.alert type="warning" title="Merchant accounts unavailable">
                    Merchant onboarding is temporarily disabled. Please check back soon.
                </x-ui.alert>
            @endunless

            @if ($featureEnabled && ! $isFullKyc)
                <x-ui.alert type="warning" title="Full verification required">
                    Merchant accounts are only available to fully verified accounts. Complete identity verification to start accepting payments.
                </x-ui.alert>
                <x-ui.card>
                    <x-ui.empty-state icon="building-storefront" title="Become a merchant"
                        description="Once you're fully verified, you can create a merchant profile and start invoicing customers instantly.">
                        <x-slot:action>
                            <a href="{{ route('settings', ['tab' => 'verification']) }}" class="inline-flex items-center gap-2 rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-600">
                                <x-heroicon-o-identification class="h-4 w-4" /> Complete verification
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
                                <h2 class="mt-4 text-xl font-bold">Become a merchant</h2>
                                <p class="mt-2 text-sm text-white/80">
                                    Turn your PoisaPay account into a payment gateway. Issue crypto invoices, share QR codes, and settle to your chosen asset.
                                </p>
                                <ul class="mt-4 space-y-2 text-sm">
                                    <li class="flex items-center gap-2"><x-heroicon-o-check-circle class="h-4 w-4" /> Shareable, QR-ready invoices</li>
                                    <li class="flex items-center gap-2"><x-heroicon-o-check-circle class="h-4 w-4" /> Instant crypto settlement</li>
                                    <li class="flex items-center gap-2"><x-heroicon-o-check-circle class="h-4 w-4" /> One-click refunds</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="lg:col-span-3">
                        <x-ui.card title="Register your business" subtitle="Tell us a little about what you sell.">
                            <form method="POST" action="{{ route('merchant.register') }}" class="space-y-5">
                                @csrf
                                <x-ui.input label="Business name" name="businessName" icon="building-storefront"
                                    :value="old('businessName')" placeholder="Acme Coffee Co." :error="$errors->first('businessName')" />

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-ui.input label="Category (optional)" name="category" icon="tag"
                                        :value="old('category')" placeholder="e.g. Retail, SaaS" :error="$errors->first('category')" />
                                    <div>
                                        <x-ui.select label="Settlement asset" name="settlementAssetId" id="regSettlementAssetId" :error="$errors->first('settlementAssetId')">
                                            <option value="">Platform default</option>
                                            @foreach ($assets as $a)
                                                <option value="{{ $a->id }}" @selected(old('settlementAssetId') == $a->id)>{{ $a->symbol }} — {{ $a->name }}</option>
                                            @endforeach
                                        </x-ui.select>
                                    </div>
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-ui.input label="Website (optional)" name="website" icon="globe-alt"
                                        :value="old('website')" placeholder="https://example.com" :error="$errors->first('website')" />
                                    <x-ui.input label="Support email (optional)" name="supportEmail" icon="envelope"
                                        :value="old('supportEmail')" placeholder="support@example.com" :error="$errors->first('supportEmail')" />
                                </div>

                                <x-ui.button type="submit" class="w-full" icon="building-storefront">Create merchant profile</x-ui.button>
                            </form>
                        </x-ui.card>
                    </div>
                </div>
            @endif
        @else
            {{-- ============================= DASHBOARD ============================= --}}
            @if ($merchant->status === \App\Enums\MerchantStatus::Pending)
                <x-ui.alert type="info" title="Application under review">
                    Your merchant profile is pending operator approval. You can set things up now — invoicing unlocks once you're approved.
                </x-ui.alert>
            @endif
            @if ($merchant->status === \App\Enums\MerchantStatus::Suspended)
                <x-ui.alert type="danger" title="Merchant account suspended">
                    {{ $merchant->suspension_reason ?: 'Your merchant account has been suspended. Contact support for details.' }}
                </x-ui.alert>
            @endif

            {{-- Stat tiles --}}
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="pp-card p-5">
                    <div class="flex items-center justify-between">
                        <span class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">Net revenue</span>
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
                        <span class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">Fees paid</span>
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
                        <span class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">Total invoices</span>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-100 text-brand-600">
                            <x-heroicon-o-document-text class="h-5 w-5" />
                        </span>
                    </div>
                    <p class="tabular mt-2 text-2xl font-bold tracking-tight text-neutral-800">{{ $stats['totalInvoices'] }}</p>
                </div>

                <div class="pp-card p-5">
                    <div class="flex items-center justify-between">
                        <span class="truncate text-xs font-semibold uppercase tracking-wide text-neutral-500">Paid invoices</span>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-emerald-100 text-emerald-500">
                            <x-heroicon-o-check-circle class="h-5 w-5" />
                        </span>
                    </div>
                    <p class="tabular mt-2 text-2xl font-bold tracking-tight text-neutral-800">{{ $stats['paidCount'] }}</p>
                </div>
            </div>

            {{-- Business profile --}}
            <x-ui.card title="Business profile">
                <x-slot:actions>
                    <x-ui.button x-on:click="showEditProfile = true" variant="secondary" size="sm" icon="pencil-square">Edit profile</x-ui.button>
                </x-slot:actions>
                <dl class="grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div>
                        <dt class="text-gray-500">Business name</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $merchant->business_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Slug</dt>
                        <dd class="mt-0.5 font-mono text-gray-900">{{ $merchant->slug }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Status</dt>
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
                        <dt class="text-gray-500">Category</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $merchant->category ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Statement descriptor</dt>
                        <dd class="mt-0.5 font-mono text-gray-900">{{ $merchant->statement_descriptor ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Processing fee</dt>
                        <dd class="mt-0.5 tabular font-medium text-gray-900">{{ $feePct }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Settlement asset</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $merchant->settlementAsset?->symbol ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Support email</dt>
                        <dd class="mt-0.5 font-medium text-gray-900">{{ $merchant->support_email ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">Website</dt>
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
                    <x-ui.card title="Create invoice" subtitle="Generate a payment request for a customer.">
                        @if ($merchant->status !== \App\Enums\MerchantStatus::Active)
                            <x-ui.empty-state icon="clock" title="Invoicing locked"
                                description="You'll be able to create invoices once your merchant account is active." />
                        @elseif ($assets->isEmpty())
                            <x-ui.empty-state icon="cube" title="No crypto assets"
                                description="Crypto assets must be configured before you can invoice." />
                        @else
                            <form method="POST" action="{{ route('merchant.invoice.create') }}" class="space-y-5">
                                @csrf
                                <div>
                                    <x-ui.select label="Asset" name="assetId" :error="$errors->first('assetId')">
                                        @foreach ($assets as $a)
                                            <option value="{{ $a->id }}" @selected(old('assetId') == $a->id)>{{ $a->symbol }} — {{ $a->name }}</option>
                                        @endforeach
                                    </x-ui.select>
                                </div>

                                <x-ui.input label="Amount" name="amount" type="text" inputmode="decimal"
                                    :value="old('amount')" placeholder="0.00" :error="$errors->first('amount')" />

                                <x-ui.input label="Reference (optional)" name="reference" icon="hashtag"
                                    :value="old('reference')" placeholder="Order #1234" :error="$errors->first('reference')"
                                    hint="Leave blank to auto-generate a unique reference." />

                                <x-ui.input label="Memo (optional)" name="memo" icon="chat-bubble-left-ellipsis"
                                    :value="old('memo')" placeholder="What's this for?" :error="$errors->first('memo')" />

                                <x-ui.button type="submit" class="w-full" icon="plus">Create invoice</x-ui.button>
                            </form>
                        @endif
                    </x-ui.card>
                </div>

                {{-- Invoices table --}}
                <div class="lg:col-span-3">
                    <x-ui.card title="Your invoices" subtitle="Your 50 most recent invoices." padding="p-5 sm:p-6">
                        @if ($invoices->isNotEmpty())
                            <x-ui.table :headers="['Reference', 'Amount', 'Fee', 'Net', 'Status', 'Payer', 'Created', '']">
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
                                                    <x-ui.button x-on:click="qr = {{ \Illuminate\Support\Js::from(['payUrl' => $invoice['payUrl'], 'amount' => $invoice['amount'], 'reference' => $invoice['reference'], 'qrSvg' => $invoice['qrSvg']]) }}; copied = false" variant="secondary" size="sm" icon="qr-code">QR / Link</x-ui.button>
                                                    <x-ui.button x-on:click="confirmingCancel = {{ \Illuminate\Support\Js::from(['id' => $invoice['id'], 'reference' => $invoice['reference']]) }}" variant="ghost" size="sm" icon="x-circle">Cancel</x-ui.button>
                                                @elseif ($invoice['status'] === 'paid' && $allowRefunds)
                                                    <x-ui.button x-on:click="confirmingRefund = {{ \Illuminate\Support\Js::from(['id' => $invoice['id'], 'reference' => $invoice['reference']]) }}" variant="ghost" size="sm" icon="arrow-uturn-left">Refund</x-ui.button>
                                                @else
                                                    <span class="text-xs text-neutral-400">—</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </x-ui.table>
                        @else
                            <x-ui.empty-state icon="document-text" title="No invoices yet"
                                description="Create your first invoice to start accepting payments." />
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
                        <h3 class="text-lg font-semibold text-gray-900">Edit business profile</h3>
                        <button type="button" x-on:click="showEditProfile = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600" aria-label="Close">
                            <x-heroicon-o-x-mark class="h-5 w-5" />
                        </button>
                    </div>
                    <form method="POST" action="{{ route('merchant.profile') }}" class="space-y-4">
                        @csrf @method('PUT')
                        <x-ui.input label="Business name" name="businessName" icon="building-storefront"
                            :value="old('businessName', $merchant->business_name)" :error="$errors->first('businessName')" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input label="Category" name="category" icon="tag"
                                :value="old('category', $merchant->category)" :error="$errors->first('category')" />
                            <div>
                                <x-ui.select label="Settlement asset" name="settlementAssetId" id="editSettlementAssetId" :error="$errors->first('settlementAssetId')">
                                    <option value="">Platform default</option>
                                    @foreach ($assets as $a)
                                        <option value="{{ $a->id }}" @selected(old('settlementAssetId', $merchant->settlement_asset_id) == $a->id)>{{ $a->symbol }} — {{ $a->name }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>
                        </div>
                        <x-ui.input label="Website" name="website" icon="globe-alt"
                            :value="old('website', $merchant->website)" placeholder="https://example.com" :error="$errors->first('website')" />
                        <x-ui.input label="Support email" name="supportEmail" icon="envelope"
                            :value="old('supportEmail', $merchant->support_email)" placeholder="support@example.com" :error="$errors->first('supportEmail')" />
                        <div class="flex justify-end gap-2 pt-1">
                            <x-ui.button type="button" variant="secondary" x-on:click="showEditProfile = false">Cancel</x-ui.button>
                            <x-ui.button type="submit" icon="check">Save changes</x-ui.button>
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
                            <h3 class="text-lg font-semibold text-gray-900">Cancel this invoice?</h3>
                            <p class="mt-2 text-sm text-gray-500">The invoice will no longer be payable. This cannot be undone.</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="confirmingCancel = null">Keep invoice</x-ui.button>
                        <form method="POST" x-bind:action="confirmingCancel ? '{{ url('/merchant/invoices') }}/' + confirmingCancel.id + '/cancel' : ''">
                            @csrf
                            <x-ui.button type="submit" variant="danger" icon="x-circle">Cancel invoice</x-ui.button>
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
                            <h3 class="text-lg font-semibold text-gray-900">Refund this invoice?</h3>
                            <p class="mt-2 text-sm text-gray-500">The payer is made whole for the full amount. Your net and the processing fee are returned. This requires sufficient balance.</p>
                        </div>
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="confirmingRefund = null">Keep payment</x-ui.button>
                        <form method="POST" x-bind:action="confirmingRefund ? '{{ url('/merchant/invoices') }}/' + confirmingRefund.id + '/refund' : ''">
                            @csrf
                            <x-ui.button type="submit" icon="arrow-uturn-left">Refund in full</x-ui.button>
                        </form>
                    </div>
                </div>
            </div>

            {{-- QR modal --}}
            <div x-show="qr" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="qr = null"></div>
                <div class="relative w-full max-w-md pp-card p-6" role="dialog" aria-modal="true">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">Scan to pay</h3>
                        <button type="button" x-on:click="qr = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>

                    <div class="flex flex-col items-center gap-4">
                        <div class="rounded-xl border border-neutral-200 bg-white p-3" x-html="qr?.qrSvg"></div>
                        <div class="text-center">
                            <p class="tabular text-lg font-semibold text-neutral-900" x-text="qr?.amount"></p>
                            <p class="font-mono text-xs text-neutral-500" x-text="qr?.reference"></p>
                        </div>
                        <div class="w-full">
                            <label class="pp-label">Payment link</label>
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
