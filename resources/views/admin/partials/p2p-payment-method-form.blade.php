{{-- Create / edit modal. Relies on the Alpine state defined by the host page:
     open, editingId, action (getter), form.*, fields[], addField(). Shared by the
     payment-methods list and the single-method detail page. --}}
<div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-black/40 p-4 sm:p-8" x-on:keydown.escape.window="open = false">
    <div class="w-full max-w-2xl rounded-2xl bg-white shadow-xl" @click.outside="open = false">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
            <h3 class="text-base font-semibold text-gray-900" x-text="editingId ? 'Edit payment method' : 'Add payment method'"></h3>
            <button type="button" class="text-gray-400 hover:text-gray-700" x-on:click="open = false"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
        </div>

        <form method="POST" :action="action" class="space-y-5 px-6 py-5">
            @csrf
            <input type="hidden" name="_method" :value="editingId ? 'PUT' : 'POST'">
            <input type="hidden" name="id" :value="editingId">

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="pp-label">Name</label>
                    <input type="text" name="name" x-model="form.name" class="pp-input" required placeholder="bKash">
                    @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="pp-label">Key</label>
                    <input type="text" name="key" x-model="form.key" class="pp-input font-mono" required placeholder="bkash" pattern="[a-z0-9_]+">
                    @error('key')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="pp-label">Type</label>
                    <select name="type" x-model="form.type" class="pp-input">
                        <option value="mobile">Mobile</option>
                        <option value="bank">Bank</option>
                        <option value="wallet">Wallet</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="pp-label">Country (ISO-2)</label>
                    <input type="text" name="country" x-model="form.country" class="pp-input" maxlength="2" placeholder="BD">
                </div>
                <div>
                    <label class="pp-label">Sort</label>
                    <input type="number" name="sort" x-model="form.sort" class="pp-input" min="0" max="999">
                </div>
            </div>

            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" x-model="form.is_active" class="rounded border-gray-300 text-brand-500 focus:ring-brand-400">
                <span class="text-sm text-gray-700">Active</span>
            </label>

            {{-- Fields editor --}}
            <div class="border-t border-gray-100 pt-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Account fields</p>
                        <p class="text-xs text-gray-500">What a user enters when saving an account on this method.</p>
                    </div>
                    <x-ui.button type="button" size="sm" variant="secondary" icon="plus" x-on:click="addField()">Add field</x-ui.button>
                </div>

                <div class="mt-3 space-y-2">
                    <template x-for="(f, i) in fields" :key="i">
                        <div class="flex items-center gap-2">
                            <input type="text" :name="`fields[${i}][key]`" x-model="f.key" placeholder="key (e.g. bank_name)" class="pp-input font-mono flex-1">
                            <input type="text" :name="`fields[${i}][label]`" x-model="f.label" placeholder="Label" class="pp-input flex-1">
                            <label class="inline-flex shrink-0 items-center gap-1.5 text-xs text-gray-600" title="Required">
                                <input type="checkbox" :name="`fields[${i}][required]`" value="1" x-model="f.required" class="rounded border-gray-300 text-brand-500 focus:ring-brand-400">
                                Req
                            </label>
                            <button type="button" class="shrink-0 text-gray-400 hover:text-red-600" x-on:click="fields.splice(i, 1)"><x-heroicon-o-trash class="h-4 w-4" /></button>
                        </div>
                    </template>
                    <p x-show="fields.length === 0" class="text-xs text-gray-400">No fields — add at least one.</p>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-gray-100 pt-4">
                <x-ui.button type="button" variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                <x-ui.button type="submit" x-text="editingId ? 'Save method' : 'Create method'">Save</x-ui.button>
            </div>
        </form>
    </div>
</div>
