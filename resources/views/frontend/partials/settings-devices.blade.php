{{-- Settings › Devices tab — devices that have signed in.
     Expects (from the settings view scope): $devices. --}}
<x-settings.section :title="__('Devices')" :description="__('Devices that have signed in to your account.')">
    <div class="space-y-2.5">
        @forelse ($devices as $d)
            <div class="flex items-center gap-3 rounded-xl border p-3 {{ $d['current'] ? 'border-emerald-200 bg-emerald-50/40' : 'border-neutral-200' }}">
                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-neutral-100 text-neutral-500">
                    <x-heroicon-o-computer-desktop class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium text-neutral-900">{{ $d['name'] ?? __('Unknown device') }}</p>
                    <p class="truncate text-xs text-neutral-500">{{ $d['ip'] ?? __('Unknown IP') }} · {{ $d['last'] ?? '—' }}</p>
                </div>
                @if ($d['current'])
                    <span class="shrink-0 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">{{ __('This device') }}</span>
                @else
                    <form method="POST" action="{{ route('settings.device.revoke', $d['id']) }}" onsubmit="return confirm('{{ __('Revoke this device?') }}')">
                        @csrf @method('DELETE')
                        <x-ui.button type="submit" variant="ghost" size="sm" icon="trash">{{ __('Revoke') }}</x-ui.button>
                    </form>
                @endif
            </div>
        @empty
            <x-ui.empty-state icon="computer-desktop" :title="__('No devices yet')"
                :description="__('Devices you sign in from will be listed here.')" />
        @endforelse
    </div>
</x-settings.section>
