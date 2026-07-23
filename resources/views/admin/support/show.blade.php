<x-layouts.admin :title="'Ticket'">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <a href="{{ route('admin.support') }}" class="inline-flex items-center gap-1 text-sm font-medium text-neutral-500 hover:text-neutral-800">
                <x-heroicon-o-arrow-left class="h-4 w-4" /> Back to tickets
            </a>
        </div>

        <x-ui.page-header :title="$ticket->subject" :subtitle="($ticket->user?->email ?? '—').' · '.ucfirst($ticket->category).' · '.ucfirst($ticket->priority).' priority'">
            <x-slot:actions>
                <x-ui.badge :color="$ticket->status->color()" dot>{{ $ticket->status->label() }}</x-ui.badge>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Actions --}}
        <x-ui.card>
            <div class="flex flex-wrap items-center gap-3">
                <form method="POST" action="{{ route('admin.support.assign', $ticket->id) }}">
                    @csrf
                    <x-ui.button type="submit" variant="secondary" size="sm" icon="user-plus">Assign to me</x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.support.status', $ticket->id) }}" class="flex items-center gap-2">
                    @csrf
                    <select name="status" class="rounded-lg border-neutral-300 text-sm">
                        @foreach (['open', 'pending', 'resolved', 'closed'] as $s)
                            <option value="{{ $s }}" @selected($ticket->status->value === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <x-ui.button type="submit" variant="secondary" size="sm">Set status</x-ui.button>
                </form>
                @if ($ticket->assignedTo)
                    <span class="text-xs text-neutral-500">Assigned to {{ $ticket->assignedTo->name }}</span>
                @endif
            </div>
        </x-ui.card>

        {{-- Thread --}}
        <x-ui.card>
            <div class="space-y-3">
                @foreach ($ticket->messages as $message)
                    <div @class(['flex', 'justify-end' => $message->is_staff])>
                        <div @class([
                            'max-w-[85%] rounded-2xl px-4 py-3',
                            'bg-brand-50 text-neutral-900' => $message->is_staff,
                            'border border-neutral-200 bg-white text-neutral-800' => ! $message->is_staff,
                        ])>
                            <p class="mb-1 text-xs font-semibold {{ $message->is_staff ? 'text-brand-600' : 'text-neutral-500' }}">
                                {{ $message->is_staff ? ($message->author_name ?? 'Staff') : ($ticket->user?->name ?? 'User') }} · {{ $message->created_at->diffForHumans() }}
                            </p>
                            <p class="whitespace-pre-line text-sm">{{ $message->body }}</p>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Staff reply --}}
            <form method="POST" action="{{ route('admin.support.reply', $ticket->id) }}" class="mt-5 border-t border-neutral-100 pt-5">
                @csrf
                <textarea name="body" required rows="3" maxlength="5000" placeholder="Reply to the user…"
                    class="w-full rounded-xl border-neutral-300 text-sm"></textarea>
                <div class="mt-2 text-right">
                    <x-ui.button type="submit" variant="primary" size="sm" icon="paper-airplane">Send reply</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layouts.admin>
