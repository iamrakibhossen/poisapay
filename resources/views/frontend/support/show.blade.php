<x-layouts.app :title="__('Ticket · :subject', ['subject' => $ticket->subject])">
    @php
        $catMeta = [
            'general' => 'chat-bubble-left-right', 'account' => 'user-circle', 'deposit' => 'arrow-down-tray',
            'withdrawal' => 'arrow-up-tray', 'card' => 'credit-card', 'kyc' => 'identification', 'other' => 'ellipsis-horizontal-circle',
        ];
        $icon = $catMeta[$ticket->category] ?? 'ellipsis-horizontal-circle';
        $closed = $ticket->status->value === 'closed';
    @endphp

    <div class="mx-auto max-w-3xl space-y-5">
        <x-ui.page-header :title="$ticket->subject" :subtitle="__('Ticket #:id', ['id' => \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($ticket->id, 0, 8))])">
            <x-slot:actions>
                <a href="{{ route('support.index') }}"><x-ui.button variant="secondary" icon="arrow-left">{{ __('Support') }}</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Meta bar --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl border border-neutral-200 bg-white p-4 shadow-[var(--shadow-card)]">
            <span class="inline-flex items-center gap-1.5 text-sm text-neutral-600">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4 text-neutral-400" />{{ ucfirst($ticket->category) }}
            </span>
            <span class="text-neutral-200">|</span>
            <span class="inline-flex items-center gap-1.5 text-sm text-neutral-600">
                @php $tone = ['low' => 'bg-green-500', 'normal' => 'bg-brand-500', 'high' => 'bg-red-500'][$ticket->priority] ?? 'bg-neutral-400'; @endphp
                <span class="h-2 w-2 rounded-full {{ $tone }}"></span>{{ ucfirst($ticket->priority) }} {{ __('priority') }}
            </span>
            <span class="text-neutral-200">|</span>
            <span class="text-sm text-neutral-500">{{ __('Opened') }} {{ $ticket->created_at->format('d M, Y') }}</span>
            <x-ui.badge :color="$ticket->status->color()" dot class="ml-auto">{{ $ticket->status->label() }}</x-ui.badge>
        </div>

        @if (session('status'))<x-ui.alert type="success">{{ session('status') }}</x-ui.alert>@endif
        @if ($errors->any())<x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>@endif

        {{-- Conversation --}}
        <div class="space-y-4">
            @foreach ($ticket->messages as $message)
                <div @class(['flex gap-3', 'flex-row-reverse' => ! $message->is_staff])>
                    <span @class([
                        'grid h-9 w-9 shrink-0 place-items-center rounded-full text-xs font-bold',
                        'bg-brand-100 text-brand-700' => $message->is_staff,
                        'bg-neutral-200 text-neutral-600' => ! $message->is_staff,
                    ])>
                        @if ($message->is_staff)<x-heroicon-s-lifebuoy class="h-5 w-5" />@else {{ \Illuminate\Support\Str::substr(auth()->user()->name, 0, 1) }}@endif
                    </span>
                    <div class="min-w-0 max-w-[85%]">
                        <div @class([
                            'rounded-2xl px-4 py-3',
                            'rounded-tl-sm bg-brand-50 text-neutral-900' => $message->is_staff,
                            'rounded-tr-sm border border-neutral-200 bg-white text-neutral-800' => ! $message->is_staff,
                        ])>
                            <p class="whitespace-pre-line text-sm">{{ $message->body }}</p>
                        </div>
                        <p @class(['mt-1 px-1 text-xs text-neutral-400', 'text-right' => ! $message->is_staff])>
                            {{ $message->is_staff ? ($message->author_name ?? __('Support team')) : __('You') }} · {{ $message->created_at->diffForHumans() }}
                        </p>
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Reply --}}
        @if (! $closed)
            <x-ui.card>
                <form method="POST" action="{{ route('support.reply', $ticket->id) }}" x-data="{ body: '' }">
                    @csrf
                    <label class="pp-label">{{ __('Reply') }}</label>
                    <textarea name="body" x-model="body" required rows="3" maxlength="5000"
                              placeholder="{{ __('Write a reply…') }}" class="pp-input"></textarea>
                    <div class="mt-3 flex items-center justify-between">
                        <p class="text-xs text-neutral-400"><span x-text="body.length"></span>/5000</p>
                        <x-ui.button type="submit" icon="paper-airplane" x-bind:disabled="!body.trim()">{{ __('Send reply') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        @else
            <div class="flex flex-col items-center gap-3 rounded-2xl border border-neutral-200 bg-neutral-50 px-4 py-6 text-center">
                <x-heroicon-o-lock-closed class="h-6 w-6 text-neutral-400" />
                <p class="text-sm text-neutral-500">{{ __('This ticket is closed. Open a new one if you still need help.') }}</p>
                <a href="{{ route('support.create') }}"><x-ui.button variant="secondary" icon="plus">{{ __('New ticket') }}</x-ui.button></a>
            </div>
        @endif
    </div>
</x-layouts.app>
