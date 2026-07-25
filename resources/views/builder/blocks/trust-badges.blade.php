<section id="{{ $node->id }}" class="pp-block py-8">
    <div class="flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-xs text-neutral-500">
        @if ($props['secure'] ?? true)
            <span class="inline-flex items-center gap-1.5"><x-heroicon-o-lock-closed class="h-4 w-4" style="color: var(--pp-accent)" /> {{ __('Secure checkout') }}</span>
        @endif
        @if ($props['refund'] ?? true)
            <span class="inline-flex items-center gap-1.5"><x-heroicon-o-arrow-uturn-left class="h-4 w-4" style="color: var(--pp-accent)" /> {{ __('14-day refund') }}</span>
        @endif
        @if ($props['instant'] ?? true)
            <span class="inline-flex items-center gap-1.5"><x-heroicon-o-bolt class="h-4 w-4" style="color: var(--pp-accent)" /> {{ __('Instant access') }}</span>
        @endif
    </div>
</section>
