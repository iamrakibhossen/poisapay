<x-layouts.app :title="'Ticket'">
    <div class="mx-auto max-w-2xl">
        <header class="mb-6">
            <a href="{{ route('support') }}" class="text-sm text-neutral-500 hover:text-neutral-900">← Back to support</a>
            <div class="mt-2 flex items-center justify-between gap-3">
                <h1 class="text-xl font-semibold tracking-tight text-neutral-900">{{ $ticket->subject }}</h1>
                <span @class([
                    'shrink-0 rounded-full px-2.5 py-1 text-xs font-medium',
                    'bg-sky-100 text-sky-700' => $ticket->status->value === 'open',
                    'bg-amber-100 text-amber-700' => $ticket->status->value === 'pending',
                    'bg-emerald-100 text-emerald-700' => $ticket->status->value === 'resolved',
                    'bg-neutral-100 text-neutral-600' => $ticket->status->value === 'closed',
                ])>{{ $ticket->status->label() }}</span>
            </div>
            <p class="text-xs text-neutral-500">{{ ucfirst($ticket->category) }} · {{ ucfirst($ticket->priority) }} priority</p>
        </header>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <div class="space-y-3">
            @foreach ($ticket->messages as $message)
                <div @class(['flex', 'justify-end' => $message->is_staff])>
                    <div @class([
                        'max-w-[85%] rounded-2xl px-4 py-3',
                        'bg-brand-50 text-neutral-900' => $message->is_staff,
                        'bg-white border border-neutral-200 text-neutral-800' => ! $message->is_staff,
                    ])>
                        <p class="mb-1 text-xs font-semibold {{ $message->is_staff ? 'text-brand-600' : 'text-neutral-500' }}">
                            {{ $message->is_staff ? ($message->author_name ?? 'Support') : 'You' }} · {{ $message->created_at->diffForHumans() }}
                        </p>
                        <p class="whitespace-pre-line text-sm">{{ $message->body }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($ticket->status->value !== 'closed')
            <form method="POST" action="{{ route('support.reply', $ticket->id) }}" class="mt-5">
                @csrf
                <textarea name="body" required rows="3" maxlength="5000" placeholder="Write a reply…" class="w-full rounded-xl border-neutral-300 text-sm"></textarea>
                <div class="mt-2 text-right">
                    <button class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white">Send reply</button>
                </div>
            </form>
        @else
            <p class="mt-5 rounded-xl border border-neutral-200 bg-neutral-50 px-4 py-3 text-center text-sm text-neutral-500">This ticket is closed.</p>
        @endif
    </div>
</x-layouts.app>
