<x-layouts.admin :title="'Feature Flags'">
    <div class="space-y-6">
        <x-ui.page-header title="Feature Flags" subtitle="Toggle platform modules and controls. Changes take effect immediately." />

        @if (session('status'))
            <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
        @endif

        @foreach ($groups as $group => $flags)
            <x-ui.card :title="$group">
                <div class="grid gap-2 sm:grid-cols-2">
                    @foreach ($flags as $key => $enabled)
                        <form method="POST" action="{{ route('admin.feature-flags.toggle') }}"
                            class="flex items-center justify-between rounded-lg border border-neutral-200 px-3.5 py-2.5">
                            @csrf
                            <input type="hidden" name="flag" value="{{ $key }}" />
                            <span class="font-mono text-xs text-neutral-700">{{ $key }}</span>
                            <button class="rounded-full px-3 py-1 text-xs font-semibold {{ $enabled ? 'bg-emerald-100 text-emerald-700' : 'bg-neutral-100 text-neutral-500' }}">
                                {{ $enabled ? 'On' : 'Off' }}
                            </button>
                        </form>
                    @endforeach
                </div>
            </x-ui.card>
        @endforeach
    </div>
</x-layouts.admin>
