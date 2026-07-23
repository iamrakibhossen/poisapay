<x-layouts.admin :title="'P2P Disputes'">
    @php $canManage = auth('admin')->user()?->can('manage-p2p') || auth('admin')->user()?->hasRole('super-admin'); @endphp
    <div class="space-y-6">
        <x-ui.page-header title="P2P Disputes" subtitle="Adjudicate contested trades. Ruling for the buyer force-releases escrow; ruling for the seller refunds it." />

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif
        @if (session('error'))<x-ui.alert type="error">{{ session('error') }}</x-ui.alert>@endif

        <div class="space-y-4">
            @forelse ($disputes as $dispute)
                @php $order = $dispute->order; @endphp
                <x-ui.card>
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs text-neutral-500">{{ $order?->ref }}</span>
                                <x-ui.badge :color="$dispute->status->color()" dot>{{ $dispute->status->label() }}</x-ui.badge>
                            </div>
                            <p class="mt-2 text-sm text-neutral-900"><span class="font-semibold">{{ $dispute->reason }}</span></p>
                            @if ($dispute->detail)<p class="mt-1 text-sm text-neutral-500">{{ $dispute->detail }}</p>@endif
                            <p class="mt-2 text-xs text-neutral-500">
                                Opened by {{ $dispute->opened_by_role }} ·
                                {{ $order?->cryptoMoney()->format() }} for {{ number_format((float) ($order?->fiat_amount ?? 0), 2) }} {{ $order?->fiat_currency }} ·
                                buyer {{ $order?->buyer?->name }} / seller {{ $order?->seller?->name }}
                            </p>
                        </div>
                        <a href="{{ route('admin.p2p-disputes.show', $dispute) }}"><x-ui.button size="sm" variant="secondary" icon="eye">Open case</x-ui.button></a>
                    </div>

                    @if ($canManage && $dispute->status->isOpen())
                        <form method="POST" action="{{ route('admin.p2p-disputes.resolve', $dispute) }}" class="mt-4 flex flex-col gap-3 border-t border-gray-200 pt-4 sm:flex-row sm:items-end">
                            @csrf
                            <div class="flex-1">
                                <label class="pp-label">Resolution note (optional)</label>
                                <input type="text" name="note" class="pp-input" placeholder="Reason for the ruling…" maxlength="255">
                            </div>
                            <div class="flex gap-2">
                                <x-ui.button type="submit" name="winner" value="buyer" variant="success" icon="lock-open"
                                    onclick="return confirm('Force-release the escrow to the BUYER?')">Rule for buyer</x-ui.button>
                                <x-ui.button type="submit" name="winner" value="seller" variant="danger" icon="arrow-uturn-left"
                                    onclick="return confirm('Refund the escrow to the SELLER?')">Rule for seller</x-ui.button>
                            </div>
                        </form>
                    @elseif (! $dispute->status->isOpen())
                        <p class="mt-3 border-t border-gray-200 pt-3 text-sm text-neutral-500">
                            Resolved{{ $dispute->resolution ? ' — '.$dispute->resolution : '' }} ({{ $dispute->resolved_at?->diffForHumans() }}).
                        </p>
                    @endif
                </x-ui.card>
            @empty
                <x-ui.card><x-ui.empty-state icon="scale" title="No disputes" description="There are no P2P disputes right now." /></x-ui.card>
            @endforelse
        </div>

        <div>{{ $disputes->links() }}</div>
    </div>
</x-layouts.admin>
