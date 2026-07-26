@php $action = trim((string) ($props['action'] ?? '')); @endphp
<section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
    <div class="mx-auto max-w-lg px-5">
        <div class="text-center">
            <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] ?? __('Get in touch') }}</h2>
            @if (! empty($props['sub']))<p class="mt-2 text-sm text-neutral-500">{{ $props['sub'] }}</p>@endif
        </div>
        <form class="mt-7 space-y-3" @if ($action) action="{{ $action }}" method="post" @else onsubmit="return false" @endif>
            <div class="grid gap-3 sm:grid-cols-2">
                <input type="text" name="name" required placeholder="{{ __('Your name') }}" class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm focus:border-neutral-400 focus:outline-none" />
                <input type="email" name="email" required placeholder="{{ __('Email') }}" class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm focus:border-neutral-400 focus:outline-none" />
            </div>
            <textarea name="message" rows="4" placeholder="{{ __('How can we help?') }}" class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm focus:border-neutral-400 focus:outline-none"></textarea>
            <button type="submit" class="w-full rounded-xl px-6 py-3 text-sm font-semibold text-white transition hover:opacity-95" style="background: var(--pp-accent)">{{ $props['btn'] ?? __('Send message') }}</button>
        </form>
    </div>
</section>
