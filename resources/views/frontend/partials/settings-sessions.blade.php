{{-- Settings › Sessions tab — active sessions + recent sign-in history.
     Expects (from the settings view scope): $sessions, $loginHistory. --}}
@if (session('status'))
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
@endif
<x-settings.section :title="__('Active sessions')" :description="__('Devices currently signed in to your account.')">
    <div class="mb-4 flex justify-end">
        <form method="POST" action="{{ route('security.sessions.logout-others') }}">
            @csrf
            <x-ui.button type="submit" variant="secondary" size="sm" icon="arrow-right-on-rectangle">{{ __('Sign out other sessions') }}</x-ui.button>
        </form>
    </div>
    <div class="space-y-2.5">
        @forelse ($sessions as $s)
            <div class="flex items-center gap-3 rounded-xl border p-3 {{ $s['current'] ? 'border-emerald-200 bg-emerald-50/40' : 'border-neutral-200' }}">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-500">
                    <x-heroicon-o-globe-alt class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-neutral-900">{{ \Illuminate\Support\Str::limit($s['agent'] ?? __('Unknown device'), 60) }}</p>
                    <p class="truncate text-xs text-neutral-500">{{ $s['ip'] ?? __('Unknown IP') }} · {{ $s['last'] }}</p>
                </div>
                @if ($s['current'])
                    <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('This device') }}</span>
                @endif
            </div>
        @empty
            <x-ui.empty-state icon="globe-alt" :title="__('No active sessions')"
                :description="__('Session records will appear here.')" />
        @endforelse
    </div>
</x-settings.section>

{{-- Recent sign-ins (login history) --}}
<x-settings.section :title="__('Recent sign-ins')" :description="__('The latest sign-ins to your account.')">
    <ul class="divide-y divide-neutral-100">
        @forelse ($loginHistory as $l)
            <li class="flex items-center justify-between gap-3 py-3 text-sm">
                <span class="text-neutral-700">{{ $l->ip_address ?? __('Unknown IP') }}{{ $l->country ? ' · '.$l->country : '' }}</span>
                <span class="flex items-center gap-2 text-xs text-neutral-400">
                    @if ($l->new_device)<x-ui.badge color="warning">{{ __('New device') }}</x-ui.badge>@endif
                    {{ $l->created_at->diffForHumans() }}
                </span>
            </li>
        @empty
            <li class="py-4 text-sm text-neutral-400">{{ __('No sign-ins recorded.') }}</li>
        @endforelse
    </ul>
</x-settings.section>
