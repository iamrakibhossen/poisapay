@props([
    'user' => null,        // any model with ->name / ->email; defaults to the given guard's user
    'guard' => null,       // resolve from a specific auth guard when $user is not passed (e.g. 'admin')
    'size' => 'md',        // avatar size — sm | md | lg
    'showEmail' => true,   // show the email line (ignored in compact mode)
    'compact' => false,    // trigger form: avatar + first name only (name hidden on mobile)
])

@php
    $user ??= $guard ? auth($guard)->user() : auth()->user();
    $displayName = $user?->name ?? __('Guest');
    $displayEmail = $user?->email;
    $firstName = \Illuminate\Support\Str::of($displayName)->trim()->explode(' ')->first();
@endphp

@if ($compact)
    <span {{ $attributes->merge(['class' => 'flex min-w-0 items-center gap-2']) }}>
        <x-ui.avatar :name="$displayName" :size="$size" />
        <span class="hidden truncate text-sm font-medium text-neutral-700 sm:block">{{ $firstName }}</span>
    </span>
@else
    <span {{ $attributes->merge(['class' => 'flex min-w-0 items-center gap-2.5']) }}>
        <x-ui.avatar :name="$displayName" :size="$size" />
        <span class="min-w-0">
            <span class="block truncate text-sm font-medium text-neutral-900">{{ $displayName }}</span>
            @if ($showEmail && $displayEmail)
                <span class="block truncate text-xs text-neutral-500">{{ $displayEmail }}</span>
            @endif
            {{ $slot }}
        </span>
    </span>
@endif
