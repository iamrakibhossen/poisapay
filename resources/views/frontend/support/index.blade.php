<x-layouts.app :title="__('Support')">
    <div class="mx-auto max-w-4xl">
        <header class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Support') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Get help from our team.') }}</p>
            </div>
            <a href="{{ route('support.create') }}" class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white">{{ __('New ticket') }}</a>
        </header>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif

        <div class="divide-y divide-neutral-100 rounded-2xl border border-neutral-200 bg-white">
            @forelse ($tickets as $ticket)
                <a href="{{ route('support.show', $ticket->id) }}" class="flex items-center justify-between px-5 py-4 hover:bg-neutral-50">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-neutral-900">{{ $ticket->subject }}</p>
                        <p class="text-xs text-neutral-500">{{ ucfirst($ticket->category) }} · {{ $ticket->messages_count }} {{ $ticket->messages_count === 1 ? __('message') : __('messages') }} · {{ $ticket->updated_at->diffForHumans() }}</p>
                    </div>
                    <span @class([
                        'shrink-0 rounded-full px-2.5 py-1 text-xs font-medium',
                        'bg-sky-100 text-sky-700' => $ticket->status->value === 'open',
                        'bg-amber-100 text-amber-700' => $ticket->status->value === 'pending',
                        'bg-emerald-100 text-emerald-700' => $ticket->status->value === 'resolved',
                        'bg-neutral-100 text-neutral-600' => $ticket->status->value === 'closed',
                    ])>{{ $ticket->status->label() }}</span>
                </a>
            @empty
                <p class="px-5 py-10 text-center text-sm text-neutral-400">{{ __('No tickets yet. Open one if you need help.') }}</p>
            @endforelse
        </div>

        <div class="mt-4">{{ $tickets->links() }}</div>
    </div>
</x-layouts.app>
