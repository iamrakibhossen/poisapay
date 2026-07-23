<x-layouts.admin :title="__('Webhook Log')">
    <div class="space-y-6">
        <a href="{{ route('admin.webhook-logs') }}" class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-800">
            <x-heroicon-o-arrow-left class="h-4 w-4" /> {{ __('Webhook Logs') }}
        </a>

        <x-ui.page-header :title="$log->route ?: __('Webhook request')" :subtitle="$log->method.' · '.$log->url">
            <x-slot:actions>
                @unless ($log->resolved)
                    <form method="POST" action="{{ route('admin.webhook-logs.resolve', $log->id) }}">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm" icon="check">{{ __('Mark resolved') }}</x-ui.button>
                    </form>
                @endunless
                <form method="POST" action="{{ route('admin.webhook-logs.delete', $log->id) }}">
                    @csrf @method('DELETE')
                    <x-ui.button type="submit" variant="ghost" size="sm" icon="trash">{{ __('Delete') }}</x-ui.button>
                </form>
            </x-slot:actions>
        </x-ui.page-header>

        @if (session('success'))<x-ui.alert type="success">{{ session('success') }}</x-ui.alert>@endif

        <x-ui.card :title="__('Summary')">
            <dl class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Provider') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $log->provider ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Status') }}</dt>
                    <dd class="mt-1"><x-ui.badge :color="$log->status >= 500 ? 'danger' : ($log->status >= 400 ? 'warning' : 'success')">{{ $log->status }}</x-ui.badge></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Resolved') }}</dt>
                    <dd class="mt-1"><x-ui.badge :color="$log->resolved ? 'success' : 'gray'" dot>{{ $log->resolved ? __('Yes') : __('No') }}</x-ui.badge></dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('Received') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $log->created_at?->format('M j, Y H:i:s') }}</dd>
                </div>
                <div class="col-span-2 sm:col-span-4">
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __('IP') }} · {{ __('Hash') }}</dt>
                    <dd class="mt-1 font-mono text-xs text-gray-600">{{ $log->ip ?: '—' }} · {{ $log->hash }}</dd>
                </div>
            </dl>
        </x-ui.card>

        @php
            $block = fn ($v) => is_array($v) ? json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : (string) $v;
        @endphp

        <div class="grid gap-6 lg:grid-cols-2">
            <x-ui.card :title="__('Payload')">
                <pre class="max-h-96 overflow-auto rounded-lg bg-gray-900 p-4 font-mono text-xs leading-relaxed text-gray-100">{{ $block($log->payload) }}</pre>
            </x-ui.card>
            <x-ui.card :title="__('Headers')" :subtitle="__('Secret-bearing headers are redacted.')">
                <pre class="max-h-96 overflow-auto rounded-lg bg-gray-900 p-4 font-mono text-xs leading-relaxed text-gray-100">{{ $block($log->headers) }}</pre>
            </x-ui.card>
        </div>

        <x-ui.card :title="__('Our response')">
            <pre class="max-h-96 overflow-auto rounded-lg bg-gray-50 p-4 font-mono text-xs leading-relaxed text-gray-700">{{ $log->response ?: '—' }}</pre>
        </x-ui.card>
    </div>
</x-layouts.admin>
