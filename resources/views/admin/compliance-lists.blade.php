<x-layouts.admin :title="'Compliance Lists'">
    <div class="space-y-6">
        <x-ui.page-header title="Sanctions &amp; Watch Lists" subtitle="Persistent denylist / watchlist / whitelist entries consulted by screening & KYT." />

        @if (session('status'))
            <x-ui.alert type="success">{{ session('status') }}</x-ui.alert>
        @endif

        <x-ui.card title="Add entry">
            <form method="POST" action="{{ route('admin.compliance-lists.store') }}" class="grid gap-3 sm:grid-cols-6">
                @csrf
                <select name="list" class="rounded-lg border-neutral-300 text-sm">
                    <option value="denylist">Denylist</option>
                    <option value="watchlist">Watchlist</option>
                    <option value="whitelist">Whitelist</option>
                </select>
                <select name="kind" class="rounded-lg border-neutral-300 text-sm">
                    <option value="name">Name</option>
                    <option value="address">Address</option>
                    <option value="country">Country</option>
                    <option value="email">Email</option>
                    <option value="user">User ID</option>
                </select>
                <input name="value" placeholder="Value" required class="rounded-lg border-neutral-300 text-sm sm:col-span-2" />
                <input name="reason" placeholder="Reason (optional)" class="rounded-lg border-neutral-300 text-sm" />
                <x-ui.button type="submit" variant="primary" size="sm">Add</x-ui.button>
            </form>
        </x-ui.card>

        @php $listColor = ['denylist' => 'danger', 'watchlist' => 'warning', 'whitelist' => 'success']; @endphp
        <x-ui.table :headers="['List', 'Kind', 'Value', 'Reason', 'Source', '']">
            @forelse ($entries as $e)
                <tr class="border-b border-gray-200 hover:bg-gray-50">
                    <td class="px-4 py-3"><x-ui.badge :color="$listColor[$e->list] ?? 'gray'" dot>{{ ucfirst($e->list) }}</x-ui.badge></td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $e->kind }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-neutral-800">{{ $e->value }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $e->reason }}</td>
                    <td class="px-4 py-3 text-sm text-neutral-500">{{ $e->source }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('admin.compliance-lists.destroy', $e->id) }}">
                            @csrf @method('DELETE')
                            <x-ui.button type="submit" variant="ghost" size="sm">Remove</x-ui.button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state icon="no-symbol" title="No list entries" description="Add sanctioned names, addresses, or countries above." /></td></tr>
            @endforelse
        </x-ui.table>

        <div>{{ $entries->links() }}</div>
    </div>
</x-layouts.admin>
