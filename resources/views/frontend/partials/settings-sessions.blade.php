{{-- Settings › Sessions tab — currently signed-in sessions.
     Expects (from the settings view scope): $sessions. --}}
<x-settings.section title="Active sessions" description="Devices currently signed in to your account.">
    <div class="space-y-2.5">
        @forelse ($sessions as $s)
            <div class="flex items-center gap-3 rounded-xl border p-3 {{ $s['current'] ? 'border-emerald-200 bg-emerald-50/40' : 'border-neutral-200' }}">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-500">
                    <x-heroicon-o-globe-alt class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-neutral-900">{{ \Illuminate\Support\Str::limit($s['agent'] ?? 'Unknown device', 60) }}</p>
                    <p class="truncate text-xs text-neutral-500">{{ $s['ip'] ?? 'Unknown IP' }} · {{ $s['last'] }}</p>
                </div>
                @if ($s['current'])
                    <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">This device</span>
                @endif
            </div>
        @empty
            <x-ui.empty-state icon="globe-alt" title="No active sessions"
                description="Session records will appear here." />
        @endforelse
    </div>
</x-settings.section>
