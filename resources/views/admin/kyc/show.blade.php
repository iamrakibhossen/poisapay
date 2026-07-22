<x-layouts.admin :title="'KYC review'">
    @php
        $documents = ['Front side' => 'front', 'Back side' => 'back', 'Selfie' => 'selfie'];
        $isPending = $profile->status->value === 'pending';
    @endphp

    <div class="mx-auto max-w-4xl space-y-6" x-data="{ rejecting: {{ $errors->has('rejectReason') ? 'true' : 'false' }} }">
        <x-ui.page-header title="Identity verification" :subtitle="$profile->full_name ?: $profile->user?->name">
            <x-slot:actions>
                <x-ui.button href="{{ route('admin.kyc') }}" variant="ghost" size="sm" icon="arrow-left">Back to queue</x-ui.button>
                @if ($isPending && (auth('admin')->user()->can('approve-kyc') || auth('admin')->user()->hasRole('super-admin')))
                    <form method="POST" action="{{ route('admin.kyc.approve', $profile->id) }}"
                        onsubmit="return confirm('Approve this applicant to {{ $profile->requested_tier->label() }}? They will be marked verified.')">
                        @csrf
                        <x-ui.button type="submit" icon="check">Approve</x-ui.button>
                    </form>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Submission details --}}
        <div class="pp-card p-6">
            <div class="mb-6 flex items-center justify-between border-b border-neutral-100 pb-4">
                <div>
                    <h2 class="text-lg font-semibold text-neutral-900">{{ $profile->full_name ?: '—' }}</h2>
                    <p class="text-sm text-neutral-500">Submitted {{ $profile->created_at->format('M j, Y g:i A') }}</p>
                </div>
                <x-ui.badge :color="$profile->status->color()" dot>{{ $profile->status->label() }}</x-ui.badge>
            </div>

            <dl class="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-sm text-neutral-500">User</dt>
                    <dd class="font-medium">
                        @if ($profile->user)
                            <a href="{{ route('admin.users.show', $profile->user) }}" class="text-brand-700 hover:text-brand-800 hover:underline">{{ $profile->user->name }}</a>
                            <span class="text-xs text-neutral-400">· {{ $profile->user->email }}</span>
                        @else
                            <span class="text-neutral-900">—</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500">Requested tier</dt>
                    <dd><x-ui.badge color="primary">{{ $profile->requested_tier->label() }}</x-ui.badge></dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500">Document type</dt>
                    <dd class="font-medium text-neutral-900">{{ $profile->document_type ? \Illuminate\Support\Str::of($profile->document_type)->replace('_', ' ')->title() : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500">Document number</dt>
                    <dd class="font-mono text-sm font-medium text-neutral-900">{{ $profile->document_number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500">Date of birth</dt>
                    <dd class="font-medium text-neutral-900">{{ $profile->date_of_birth?->format('d M Y') ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500">Country</dt>
                    <dd class="font-medium text-neutral-900">{{ $profile->country ?: '—' }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-sm text-neutral-500">Address</dt>
                    <dd class="font-medium text-neutral-900">{{ $profile->address ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500">Liveness</dt>
                    <dd><x-ui.badge :color="$profile->liveness_passed ? 'success' : 'gray'">{{ $profile->liveness_passed ? 'Passed' : 'Not run' }}</x-ui.badge></dd>
                </div>
                <div>
                    <dt class="text-sm text-neutral-500">Reviewed</dt>
                    <dd class="font-medium text-neutral-900">
                        @if ($profile->reviewed_at)
                            {{ $profile->reviewed_at->format('M j, Y') }} @if ($profile->reviewedBy)· {{ $profile->reviewedBy->name }}@endif
                        @else
                            <span class="text-neutral-400">—</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($profile->rejection_reason)
                <div class="mt-6 rounded-lg border border-rose-100 bg-rose-50 p-4">
                    <p class="mb-1 text-sm font-medium text-rose-700">Rejection reason</p>
                    <p class="whitespace-pre-line text-rose-800">{{ $profile->rejection_reason }}</p>
                </div>
            @endif
        </div>

        {{-- Documents --}}
        <div class="pp-card p-6">
            <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-neutral-500">Documents</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                @foreach ($documents as $label => $slot)
                    <div>
                        <p class="mb-2 text-sm text-neutral-500">{{ $label }}</p>
                        @if ($profile->documentPath($slot))
                            <a href="{{ route('admin.kyc.file', ['id' => $profile->id, 'slot' => $slot]) }}" target="_blank"
                                class="group block overflow-hidden rounded-lg border border-neutral-200">
                                <img src="{{ route('admin.kyc.file', ['id' => $profile->id, 'slot' => $slot]) }}" alt="{{ $label }}"
                                    class="h-40 w-full object-cover transition group-hover:opacity-90" />
                            </a>
                        @else
                            <div class="flex h-40 items-center justify-center rounded-lg border border-dashed border-neutral-200 text-sm text-neutral-400">Not provided</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Reject --}}
        @if ($isPending && (auth('admin')->user()->can('approve-kyc') || auth('admin')->user()->hasRole('super-admin')))
            <div class="pp-card p-6">
                <button type="button" x-on:click="rejecting = ! rejecting" class="flex items-center gap-2 text-sm font-medium text-rose-600 hover:text-rose-800">
                    <x-heroicon-o-x-circle class="h-5 w-5" /> Reject this submission
                </button>
                <form method="POST" action="{{ route('admin.kyc.reject', $profile->id) }}" class="mt-4 space-y-3" x-show="rejecting" x-cloak>
                    @csrf
                    <x-ui.textarea label="Reason" name="rejectReason" :rows="3" :value="old('rejectReason')"
                        placeholder="Document unreadable / details mismatch / name doesn't match…" :error="$errors->first('rejectReason')" />
                    <x-ui.button type="submit" variant="danger" icon="x-mark">Confirm rejection</x-ui.button>
                </form>
            </div>
        @endif
    </div>
</x-layouts.admin>
