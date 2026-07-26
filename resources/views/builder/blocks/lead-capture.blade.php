@php $action = trim((string) ($props['action'] ?? '')); @endphp
<section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
    <div class="mx-auto max-w-lg px-5 text-center">
        <h2 class="text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] ?? __('Join the list') }}</h2>
        @if (! empty($props['sub']))<p class="mx-auto mt-2 max-w-md text-sm text-neutral-500">{{ $props['sub'] }}</p>@endif
        <form class="mx-auto mt-6 flex max-w-md flex-col gap-2.5 sm:flex-row"
            @if ($action) action="{{ $action }}" method="post" @else onsubmit="return false" @endif>
            <input type="email" name="email" required placeholder="{{ $props['placeholder'] ?? __('you@email.com') }}" class="w-full rounded-xl border border-neutral-200 px-4 py-3 text-sm focus:border-neutral-400 focus:outline-none" />
            <button type="submit" class="shrink-0 rounded-xl px-6 py-3 text-sm font-semibold text-white transition hover:opacity-95" style="background: var(--pp-accent)">{{ $props['btn'] ?? __('Subscribe') }}</button>
        </form>
        @if (! empty($props['note']))<p class="mt-3 text-xs text-neutral-400">{{ $props['note'] }}</p>@endif
    </div>
</section>
