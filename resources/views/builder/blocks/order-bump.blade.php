@php $bump = $ctx->offers['bump'] ?? null; @endphp
@if ($bump || $ctx->editing)
    <section id="{{ $node->id }}" class="pp-block py-8">
        <div class="mx-auto max-w-md px-5">
            <div class="rounded-2xl border-2 border-dashed p-5" style="border-color: color-mix(in srgb, var(--pp-accent) 45%, transparent); background: color-mix(in srgb, var(--pp-accent) 5%, transparent)">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-md text-white" style="background: var(--pp-accent)"><x-heroicon-s-plus class="h-4 w-4" /></span>
                    <div class="min-w-0">
                        @if (! empty($props['note']))<p class="text-[11px] font-semibold uppercase tracking-wide" style="color: var(--pp-accent)">{{ $props['note'] }}</p>@endif
                        <p class="text-sm font-bold text-neutral-900">{{ $bump['headline'] ?? __('Add this to your order') }}</p>
                        @if (! empty($bump['description']))<p class="mt-1 text-sm text-neutral-600">{{ $bump['description'] }}</p>@endif
                        @if (! empty($bump['price']))<p class="mt-2 text-sm font-bold" style="color: var(--pp-accent)">{{ $bump['price'] }}</p>@endif
                        @unless ($bump)<p class="mt-1 text-xs text-neutral-400">{{ __('Configure an order bump under Offers to show it here.') }}</p>@endunless
                    </div>
                </div>
            </div>
        </div>
    </section>
@endif
