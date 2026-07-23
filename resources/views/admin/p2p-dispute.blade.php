<x-layouts.admin :title="__('Dispute case')">
    @php
        $order = $dispute->order;
        $canManage = auth('admin')->user()?->can('manage-p2p') || auth('admin')->user()?->hasRole('super-admin');
    @endphp
    <div class="space-y-6">
        <x-ui.page-header :title="__('Dispute — :ref', ['ref' => $order?->ref ?? ''])" :subtitle="__('Full case history: timeline, chat transcript and evidence. Rule for the buyer to force-release, or the seller to refund.')">
            <x-slot:actions>
                <a href="{{ route('admin.p2p-disputes') }}"><x-ui.button variant="secondary" icon="arrow-left">{{ __('All disputes') }}</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif
        @if (session('error'))<x-ui.alert type="error">{{ session('error') }}</x-ui.alert>@endif

        <div class="grid gap-6 lg:grid-cols-[1fr_1.2fr]">
            {{-- Case summary + actions --}}
            <div class="space-y-4">
                <x-ui.card>
                    <div class="flex items-center gap-2">
                        <x-ui.badge :color="$dispute->status->color()" dot>{{ $dispute->status->label() }}</x-ui.badge>
                        @if ($dispute->assignedAdmin)<span class="text-xs text-neutral-500">{{ __('assigned to') }} {{ $dispute->assignedAdmin->name }}</span>@endif
                    </div>
                    <p class="mt-3 text-sm font-semibold text-neutral-900">{{ $dispute->reason }}</p>
                    @if ($dispute->detail)<p class="mt-1 text-sm text-neutral-500">{{ $dispute->detail }}</p>@endif
                    <p class="mt-2 text-xs text-neutral-500">{{ __('Opened by') }} {{ $dispute->opened_by_role }} · {{ $dispute->created_at?->diffForHumans() }}</p>

                    <dl class="mt-4 divide-y divide-neutral-100 text-sm">
                        <div class="flex justify-between py-2"><dt class="text-neutral-500">{{ __('Amount') }}</dt><dd class="tabular text-neutral-900">{{ $order?->cryptoMoney()->format() }}</dd></div>
                        <div class="flex justify-between py-2"><dt class="text-neutral-500">{{ __('Fiat') }}</dt><dd class="tabular text-neutral-700">{{ number_format((float) ($order?->fiat_amount ?? 0), 2) }} {{ $order?->fiat_currency }}</dd></div>
                        <div class="flex justify-between py-2"><dt class="text-neutral-500">{{ __('Buyer') }}</dt><dd class="text-neutral-900">{{ $order?->buyer?->name }}</dd></div>
                        <div class="flex justify-between py-2"><dt class="text-neutral-500">{{ __('Seller') }}</dt><dd class="text-neutral-900">{{ $order?->seller?->name }}</dd></div>
                    </dl>

                    @if ($canManage && $dispute->status->isOpen())
                        @if ($dispute->status->value === 'open')
                            <form method="POST" action="{{ route('admin.p2p-disputes.assign', $dispute) }}" class="mt-4">
                                @csrf
                                <x-ui.button type="submit" variant="secondary" icon="hand-raised" class="w-full">{{ __('Take case (mark under review)') }}</x-ui.button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.p2p-disputes.resolve', $dispute) }}" class="mt-4 space-y-3 border-t border-neutral-100 pt-4">
                            @csrf
                            <div>
                                <label class="pp-label">{{ __('Resolution note') }}</label>
                                <input type="text" name="note" class="pp-input" placeholder="{{ __('Reason for the ruling…') }}" maxlength="255">
                            </div>
                            <div class="flex gap-2">
                                <x-ui.button type="submit" name="winner" value="buyer" variant="success" icon="lock-open" class="flex-1"
                                    onclick="return confirm('Force-release the escrow to the BUYER?')">{{ __('Rule for buyer') }}</x-ui.button>
                                <x-ui.button type="submit" name="winner" value="seller" variant="danger" icon="arrow-uturn-left" class="flex-1"
                                    onclick="return confirm('Refund the escrow to the SELLER?')">{{ __('Rule for seller') }}</x-ui.button>
                            </div>
                        </form>
                    @elseif (! $dispute->status->isOpen())
                        <p class="mt-4 border-t border-neutral-100 pt-3 text-sm text-neutral-500">
                            {{ __('Resolved') }}{{ $dispute->resolution ? ' — '.$dispute->resolution : '' }} ({{ $dispute->resolved_at?->diffForHumans() }}).
                        </p>
                    @endif
                </x-ui.card>

                <x-ui.card>
                    <h3 class="text-sm font-semibold text-neutral-900">{{ __('Evidence') }}</h3>
                    <div class="mt-3 space-y-2">
                        @forelse ($dispute->evidence as $ev)
                            <div class="flex items-center justify-between rounded-lg border border-neutral-200 px-3 py-2 text-sm">
                                <div class="min-w-0 truncate">
                                    <span class="font-medium text-neutral-700">{{ ucfirst($ev->uploader_role) }}</span>
                                    @if ($ev->note)<span class="text-neutral-500"> — {{ $ev->note }}</span>@endif
                                    <span class="text-xs text-neutral-400"> · {{ $ev->created_at?->diffForHumans() }}</span>
                                </div>
                                <a href="{{ route('admin.p2p-disputes.evidence', $ev) }}" target="_blank" class="ml-3 shrink-0 text-brand-600 hover:underline">{{ __('Download') }}</a>
                            </div>
                        @empty
                            <p class="text-sm text-neutral-400">{{ __('No evidence uploaded.') }}</p>
                        @endforelse
                    </div>
                </x-ui.card>
            </div>

            {{-- Timeline + chat transcript --}}
            <div class="space-y-4">
                <x-ui.card>
                    <h3 class="text-sm font-semibold text-neutral-900">{{ __('Order timeline') }}</h3>
                    <ol class="mt-3 space-y-3">
                        @foreach ($order?->events->sortBy('created_at') ?? [] as $ev)
                            <li class="flex gap-3 text-sm">
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-brand-400"></span>
                                <div>
                                    <p class="text-neutral-800">{{ $ev->from_status ? $ev->from_status.' → ' : '' }}<span class="font-semibold">{{ $ev->to_status }}</span> <span class="text-xs text-neutral-400">({{ $ev->actor_type }})</span></p>
                                    @if ($ev->note)<p class="text-xs text-neutral-500">{{ $ev->note }}</p>@endif
                                    <p class="text-xs text-neutral-400">{{ $ev->created_at?->format('d M H:i') }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </x-ui.card>

                <x-ui.card>
                    <h3 class="text-sm font-semibold text-neutral-900">{{ __('Chat transcript') }}</h3>
                    <div class="mt-3 max-h-96 space-y-2 overflow-y-auto">
                        @forelse ($order?->messages->sortBy('created_at') ?? [] as $m)
                            <div class="text-sm">
                                <span class="font-medium text-neutral-600">{{ $m->sender_type === 'system' ? __('System') : ($m->sender_id === $order->buyer_id ? __('Buyer') : __('Seller')) }}:</span>
                                <span class="text-neutral-800">{{ $m->body ?: __('[attachment]') }}</span>
                                <span class="text-xs text-neutral-400">· {{ $m->created_at?->format('d M H:i') }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-neutral-400">{{ __('No messages.') }}</p>
                        @endforelse
                    </div>
                </x-ui.card>
            </div>
        </div>
    </div>
</x-layouts.admin>
