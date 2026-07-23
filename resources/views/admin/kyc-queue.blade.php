<x-layouts.admin :title="__('KYC Queue')">
    <div class="space-y-6" x-data="{ rejectingId: null }">
        <x-ui.page-header :title="__('KYC Queue')" :subtitle="__('Review identity submissions and gate money-movement access.')" />

        {{-- Status tabs (query-string filter) --}}
        <div class="flex flex-wrap gap-1 rounded-xl bg-neutral-100 p-1">
            @foreach ($tabs as $key => $count)
                <a href="{{ route('admin.kyc', ['status' => $key]) }}"
                    @class([
                        'flex items-center gap-2 rounded-lg px-3.5 py-2 text-sm font-medium capitalize transition',
                        'bg-white text-neutral-900 shadow-sm' => $status === $key,
                        'text-neutral-500 hover:text-neutral-800' => $status !== $key,
                    ])>
                    {{ $key }}
                    <span class="rounded-full bg-neutral-200 px-1.5 text-xs">{{ $count }}</span>
                </a>
            @endforeach
        </div>

        <x-ui.table :headers="[__('Applicant'), __('Requested tier'), __('Document'), __('Status'), __('Submitted'), '']">
            @forelse ($profiles as $profile)
                <tr class="hover:bg-neutral-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <x-ui.avatar :name="$profile->user->name" size="sm" />
                            <div>
                                <p class="text-sm font-medium text-neutral-900">{{ $profile->user->name }}</p>
                                <p class="text-xs text-neutral-500">{{ $profile->user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3"><x-ui.badge color="primary">{{ $profile->requested_tier->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-600">
                        <span class="capitalize">{{ $profile->document_type ?? '—' }}</span>
                        @if ($profile->document_number)<span class="block font-mono text-xs text-neutral-400">{{ $profile->document_number }}</span>@endif
                    </td>
                    <td class="px-4 py-3"><x-ui.badge :color="$profile->status->color()" dot>{{ $profile->status->label() }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $profile->created_at->diffForHumans() }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-2">
                            <x-ui.button href="{{ route('admin.kyc.show', $profile->id) }}" variant="secondary" size="sm" icon="eye">{{ __('Review') }}</x-ui.button>
                            @if ($profile->status->value === 'pending')
                                <form method="POST" action="{{ route('admin.kyc.approve', $profile->id) }}"
                                    onsubmit="return confirm('{{ __('Approve this applicant to') }} {{ $profile->requested_tier->label() }}?')">
                                    @csrf
                                    <x-ui.button type="submit" variant="success" size="sm" icon="check">{{ __('Approve') }}</x-ui.button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state icon="identification" :title="__('No submissions')" :description="__('Nothing in this queue.')" /></td></tr>
            @endforelse
        </x-ui.table>

        {{ $profiles->links() }}

        {{-- Reject modal (Alpine-driven; submits the reason directly to the reject route) --}}
        @foreach ($profiles as $profile)
            @if ($profile->status->value === 'pending')
                <div x-show="rejectingId === '{{ $profile->id }}'" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-500/60" x-on:click="rejectingId = null"></div>
                    <div class="relative w-full max-w-md pp-card p-6" role="dialog" aria-modal="true">
                        <div class="mb-4 flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-neutral-900">{{ __('Reject verification') }}</h3>
                            <button type="button" x-on:click="rejectingId = null" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                        </div>
                        <form method="POST" action="{{ route('admin.kyc.reject', $profile->id) }}" class="space-y-4">
                            @csrf
                            <p class="text-sm text-neutral-500">{{ __('Provide a reason. The applicant will be notified and can resubmit.') }}</p>
                            <x-ui.textarea :label="__('Reason')" name="rejectReason" :rows="3" :placeholder="__('Document unreadable / details mismatch…')" :error="$errors->first('rejectReason')" />
                            <div class="flex justify-end gap-2">
                                <x-ui.button type="button" variant="secondary" x-on:click="rejectingId = null">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="submit" variant="danger">{{ __('Reject verification') }}</x-ui.button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endforeach
    </div>
</x-layouts.admin>
