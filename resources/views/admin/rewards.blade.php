<x-layouts.admin :title="'Rewards'">
    @php
        $canManage = auth('admin')->user()?->can('manage-rewards') || auth('admin')->user()?->hasRole('super-admin');
        // Which modal (if any) submitted and failed validation — reopen it.
        $failedForm = $errors->any() ? old('_form') : null;
    @endphp

    {{-- Alpine is light UI only: modal open/close + prefill for edit. Both forms POST traditionally. --}}
    <div x-data="{
            campaign: {
                open: {{ $failedForm === 'campaign' ? 'true' : 'false' }},
                editingId: '{{ old('id') }}',
                form: {
                    key: @js(old('key', '')),
                    name: @js(old('name', '')),
                    type: @js(old('type', 'fixed')),
                    asset_id: @js(old('asset_id', '')),
                    amount: @js(old('amount', '')),
                    rate_bps: @js(old('rate_bps', '')),
                    min_spend: @js(old('min_spend', '')),
                    max_reward: @js(old('max_reward', '')),
                    starts_at: @js(old('starts_at', '')),
                    ends_at: @js(old('ends_at', '')),
                    is_active: {{ old('_form') === 'campaign' ? (old('is_active') ? 'true' : 'false') : 'true' }},
                },
                create() {
                    this.editingId = '';
                    this.form = { key: '', name: '', type: 'fixed', asset_id: '', amount: '', rate_bps: '', min_spend: '', max_reward: '', starts_at: '', ends_at: '', is_active: true };
                    this.open = true;
                },
                edit(c) {
                    this.editingId = c.id;
                    this.form = { key: c.key, name: c.name, type: c.type, asset_id: c.asset_id ?? '', amount: c.amount ?? '', rate_bps: c.rate_bps ?? '', min_spend: c.min_spend ?? '', max_reward: c.max_reward ?? '', starts_at: c.starts_at ?? '', ends_at: c.ends_at ?? '', is_active: c.is_active };
                    this.open = true;
                },
            },
            grant: {
                open: {{ $failedForm === 'grant' ? 'true' : 'false' }},
                form: {
                    grantEmail: @js(old('grantEmail', '')),
                    grantAssetId: @js(old('grantAssetId', '')),
                    grantAmount: @js(old('grantAmount', '')),
                    grantReason: @js(old('grantReason', '')),
                },
                openGrant() {
                    this.form = { grantEmail: '', grantAssetId: '', grantAmount: '', grantReason: '' };
                    this.open = true;
                },
            },
        }" class="space-y-6">
        <x-ui.page-header title="Rewards" subtitle="Campaigns, grants and referrals." />

        {{-- Stat cards --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card label="Active campaigns" :value="number_format($stats['activeCampaigns'])" icon="gift" accent="brand" />
            <x-ui.stat-card label="Total grants" :value="number_format($stats['grants'])" icon="banknotes" accent="emerald" />
            <x-ui.stat-card label="Referrals" :value="number_format($stats['referrals'])" icon="user-group" accent="amber" />
            <x-ui.stat-card label="Rewarded referrals" :value="number_format($stats['rewardedReferrals'])" icon="check-badge" accent="brand" />
        </div>

        {{-- DollarHub shell: sticky vertical section nav + content panel. --}}
        <div class="grid gap-6 lg:grid-cols-5">
            {{-- Vertical section navigation --}}
            <nav class="flex gap-1 overflow-x-auto rounded-xl border border-gray-200 bg-white p-2 lg:sticky lg:top-6 lg:col-span-1 lg:flex-col lg:self-start lg:overflow-visible">
                @foreach (['campaigns' => ['label' => 'Campaigns', 'icon' => 'gift'], 'grants' => ['label' => 'Grants', 'icon' => 'banknotes'], 'referrals' => ['label' => 'Referrals', 'icon' => 'user-group']] as $key => $meta)
                    <a
                        href="{{ route('admin.rewards', ['tab' => $key]) }}"
                        @class([
                            'flex shrink-0 items-center gap-2.5 rounded-lg px-3.5 py-2.5 text-sm font-medium transition lg:w-full',
                            'bg-brand-500 text-ink-900' => $tab === $key,
                            'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900' => $tab !== $key,
                        ])
                    >
                        <x-dynamic-component :component="'heroicon-o-'.$meta['icon']" class="h-5 w-5 shrink-0" />
                        <span>{{ $meta['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Active section content --}}
            <div class="space-y-6 lg:col-span-4">

                {{-- Section actions --}}
                @if ($canManage)
                    <div class="flex flex-wrap justify-end gap-2">
                        @if ($tab === 'campaigns')
                            <x-ui.button x-on:click="campaign.create()" icon="plus" size="sm">New campaign</x-ui.button>
                        @elseif ($tab === 'grants')
                            <x-ui.button x-on:click="grant.openGrant()" icon="plus" size="sm">Manual grant</x-ui.button>
                        @endif
                    </div>
                @endif

                {{-- CAMPAIGNS TAB --}}
                @if ($tab === 'campaigns')
                    <x-ui.table :headers="['Key', 'Name', 'Type', 'Asset', 'Reward', 'Limits', 'Status', '']">
                        @forelse ($campaigns as $c)
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="px-3 py-3 font-mono text-xs text-neutral-600">{{ $c->key }}</td>
                                <td class="px-3 py-3 text-sm font-medium text-neutral-900">{{ $c->name }}</td>
                                <td class="px-3 py-3"><x-ui.badge :color="$c->type === 'fixed' ? 'info' : 'indigo'">{{ ucfirst($c->type) }}</x-ui.badge></td>
                                <td class="px-3 py-3 text-sm text-neutral-600">{{ $c->asset?->symbol ?? 'spend asset' }}</td>
                                <td class="px-3 py-3 tabular text-sm font-semibold text-neutral-900">
                                    @if ($c->type === 'fixed')
                                        {{ $c->fixedMoney()?->format() ?? '—' }}
                                    @else
                                        {{ rtrim(rtrim(number_format($c->rate_bps / 100, 2), '0'), '.') }}%
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-xs text-neutral-500">
                                    @if ($c->min_spend !== null && $c->asset)
                                        <span class="block">min {{ $c->asset->money($c->min_spend)->format() }}</span>
                                    @endif
                                    @if ($c->max_reward !== null && $c->asset)
                                        <span class="block">max {{ $c->asset->money($c->max_reward)->format() }}</span>
                                    @endif
                                    @if ($c->min_spend === null && $c->max_reward === null)—@endif
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        @if ($canManage)
                                            <form method="POST" action="{{ route('admin.rewards.campaign.toggle', $c->id) }}">
                                                @csrf
                                                <button type="submit" class="inline-flex">
                                                    <x-ui.badge :color="$c->is_active ? 'success' : 'gray'" dot>{{ $c->is_active ? 'Active' : 'Paused' }}</x-ui.badge>
                                                </button>
                                            </form>
                                        @else
                                            <x-ui.badge :color="$c->is_active ? 'success' : 'gray'" dot>{{ $c->is_active ? 'Active' : 'Paused' }}</x-ui.badge>
                                        @endif
                                        @if ($c->isLive())<x-ui.badge color="info">Live</x-ui.badge>@endif
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-right">
                                    @if ($canManage)
                                        <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                                            x-on:click="campaign.edit({{ Illuminate\Support\Js::from(['id' => $c->id, 'key' => $c->key, 'name' => $c->name, 'type' => $c->type, 'asset_id' => $c->asset_id, 'amount' => ($c->amount !== null && $c->asset) ? $c->asset->money($c->amount)->toDecimal() : '', 'rate_bps' => $c->rate_bps !== null ? (string) $c->rate_bps : '', 'min_spend' => ($c->min_spend !== null && $c->asset) ? $c->asset->money($c->min_spend)->toDecimal() : '', 'max_reward' => ($c->max_reward !== null && $c->asset) ? $c->asset->money($c->max_reward)->toDecimal() : '', 'starts_at' => $c->starts_at?->format('Y-m-d\TH:i'), 'ends_at' => $c->ends_at?->format('Y-m-d\TH:i'), 'is_active' => (bool) $c->is_active]) }})">Edit</x-ui.button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-ui.empty-state icon="gift" title="No campaigns" description="Create a reward campaign to start granting rewards." /></td></tr>
                        @endforelse
                    </x-ui.table>
                @endif

                {{-- GRANTS TAB --}}
                @if ($tab === 'grants')
                    <x-ui.table :headers="['User', 'Type', 'Amount', 'Granted', '']">
                        @forelse ($grants as $grant)
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-3">
                                        <x-ui.avatar :name="$grant->user?->name ?? '?'" size="sm" />
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-neutral-900">{{ $grant->user?->name ?? '—' }}</p>
                                            <p class="truncate text-xs text-neutral-500">{{ $grant->user?->email }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3"><x-ui.badge color="gray">{{ $grant->type }}</x-ui.badge></td>
                                <td class="px-3 py-3 tabular text-sm font-semibold text-neutral-900">{{ $grant->asset?->money($grant->amount)->format() ?? '—' }}</td>
                                <td class="px-3 py-3 text-xs text-neutral-500">{{ $grant->created_at?->diffForHumans() }}</td>
                                <td class="px-3 py-3"></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty-state icon="banknotes" title="No grants" description="No reward grants have been issued yet." /></td></tr>
                        @endforelse
                    </x-ui.table>

                    {{ $grants->links() }}
                @endif

                {{-- REFERRALS TAB --}}
                @if ($tab === 'referrals')
                    <x-ui.table :headers="['Referrer', 'Referee', 'Code', 'Status', 'Created']">
                        @forelse ($referrals as $referral)
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="px-3 py-3">
                                    <p class="truncate text-sm text-neutral-800">{{ $referral->referrer?->name ?? '—' }}</p>
                                    <p class="truncate text-xs text-neutral-500">{{ $referral->referrer?->email }}</p>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="truncate text-sm text-neutral-800">{{ $referral->referee?->name ?? '—' }}</p>
                                    <p class="truncate text-xs text-neutral-500">{{ $referral->referee?->email }}</p>
                                </td>
                                <td class="px-3 py-3 font-mono text-xs text-neutral-600">{{ $referral->code }}</td>
                                <td class="px-3 py-3"><x-ui.badge :color="$referral->status->color()" dot>{{ $referral->status->label() }}</x-ui.badge></td>
                                <td class="px-3 py-3 text-xs text-neutral-500">{{ $referral->created_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-ui.empty-state icon="user-group" title="No referrals" description="No referrals have been recorded yet." /></td></tr>
                        @endforelse
                    </x-ui.table>

                    {{ $referrals->links() }}
                @endif

            </div> {{-- /content panel --}}
        </div> {{-- /rewards grid --}}

        {{-- Campaign create / edit modal --}}
        @if ($canManage)
            <div x-show="campaign.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="campaign.open = false"></div>
                <div class="relative w-full max-w-lg pp-card p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900" x-text="campaign.editingId ? 'Edit campaign' : 'New campaign'"></h3>
                        <button type="button" x-on:click="campaign.open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <form method="POST" action="{{ route('admin.rewards.campaign.save') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_form" value="campaign" />
                        <input type="hidden" name="id" :value="campaign.editingId" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.select label="Key" name="key" x-model="campaign.form.key" x-bind:disabled="!!campaign.editingId" :error="$errors->first('key')">
                                <option value="">Select…</option>
                                @foreach (['welcome', 'referral_referrer', 'referral_referee', 'cashback', 'manual', 'daily'] as $k)
                                    <option value="{{ $k }}">{{ $k }}</option>
                                @endforeach
                            </x-ui.select>
                            <x-ui.input label="Name" name="name" x-model="campaign.form.name" placeholder="Welcome bonus" :error="$errors->first('name')" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.select label="Type" name="type" x-model="campaign.form.type" :error="$errors->first('type')">
                                <option value="fixed">Fixed</option>
                                <option value="percentage">Percentage</option>
                            </x-ui.select>
                            <x-ui.select label="Asset" name="asset_id" x-model="campaign.form.asset_id" :error="$errors->first('asset_id')">
                                <option value="" x-text="campaign.form.type === 'percentage' ? 'Spend asset (dynamic)' : 'Select…'"></option>
                                @foreach ($assets as $asset)
                                    <option value="{{ $asset->id }}">{{ $asset->symbol }}</option>
                                @endforeach
                            </x-ui.select>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div x-show="campaign.form.type === 'fixed'">
                                <x-ui.input label="Amount" name="amount" x-model="campaign.form.amount" placeholder="5.00" :error="$errors->first('amount')" />
                            </div>
                            <div x-show="campaign.form.type !== 'fixed'">
                                <x-ui.input label="Rate (bps)" type="number" min="1" max="10000" name="rate_bps" x-model="campaign.form.rate_bps" placeholder="200" :error="$errors->first('rate_bps')" />
                            </div>
                            <x-ui.input label="Min spend (optional)" name="min_spend" x-model="campaign.form.min_spend" placeholder="0" :error="$errors->first('min_spend')" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input label="Max reward (optional)" name="max_reward" x-model="campaign.form.max_reward" placeholder="0" :error="$errors->first('max_reward')" />
                            <label class="flex items-end gap-2 text-sm text-neutral-700 pb-2"><input type="checkbox" name="is_active" value="1" x-model="campaign.form.is_active" class="rounded border-neutral-300 text-brand-500 focus:ring-brand-500"> Active</label>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input label="Starts at (optional)" type="datetime-local" name="starts_at" x-model="campaign.form.starts_at" :error="$errors->first('starts_at')" />
                            <x-ui.input label="Ends at (optional)" type="datetime-local" name="ends_at" x-model="campaign.form.ends_at" :error="$errors->first('ends_at')" />
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button type="button" variant="secondary" x-on:click="campaign.open = false">Cancel</x-ui.button>
                            <x-ui.button type="submit" x-text="campaign.editingId ? 'Save changes' : 'Create campaign'"></x-ui.button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Manual grant modal --}}
            <div x-show="grant.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="grant.open = false"></div>
                <div class="relative w-full max-w-md pp-card p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">Manual grant</h3>
                        <button type="button" x-on:click="grant.open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <p class="mb-4 text-sm text-neutral-500">Issue a one-off reward to a user. This is an auditable treasury payout.</p>
                    <form method="POST" action="{{ route('admin.rewards.grant') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_form" value="grant" />
                        <x-ui.input label="User email" type="email" name="grantEmail" x-model="grant.form.grantEmail" placeholder="user@example.com" :error="$errors->first('grantEmail')" />
                        <x-ui.select label="Asset" name="grantAssetId" x-model="grant.form.grantAssetId" :error="$errors->first('grantAssetId')">
                            <option value="">Select…</option>
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->symbol }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.input label="Amount" name="grantAmount" x-model="grant.form.grantAmount" placeholder="5.00" :error="$errors->first('grantAmount')" />
                        <x-ui.textarea label="Reason (optional)" name="grantReason" x-model="grant.form.grantReason" :rows="2" placeholder="e.g. Goodwill credit" :error="$errors->first('grantReason')" />
                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button type="button" variant="secondary" x-on:click="grant.open = false">Cancel</x-ui.button>
                            <x-ui.button type="submit" icon="check">Grant reward</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.admin>
