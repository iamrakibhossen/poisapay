<x-layouts.admin :title="'Pages'">
    {{-- Alpine is light UI only: modal open/close + prefill for edit. The form POSTs traditionally. --}}
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            editingId: '{{ old('id') }}',
            form: {
                title: @js(old('title', '')),
                slug: @js(old('slug', '')),
                status: '{{ old('status', 'published') }}',
                meta_description: @js(old('meta_description', '')),
                content: @js(old('content', '')),
            },
            create() { this.editingId = ''; this.form = { title: '', slug: '', status: 'published', meta_description: '', content: '' }; this.open = true; },
            edit(p) { this.editingId = p.id; this.form = { title: p.title, slug: p.slug, status: p.status, meta_description: p.meta_description, content: p.content }; this.open = true; },
            slugify() { if (! this.editingId && ! this.form.slug) { this.form.slug = this.form.title.toLowerCase().trim().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); } },
        }" class="space-y-6">
        <x-ui.page-header title="Pages" subtitle="Marketing and legal content served on the public site.">
            <x-slot:actions>
                <x-ui.button x-on:click="create()" icon="plus" size="sm">New page</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.table :headers="['Title', 'Slug', 'Status', 'Updated', '']">
            @forelse ($pages as $page)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-neutral-900">{{ $page->title }}</p>
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('page.show', $page->slug) }}" target="_blank" class="font-mono text-xs text-brand-600 hover:underline">/p/{{ $page->slug }}</a>
                    </td>
                    <td class="px-4 py-3">
                        <x-ui.badge :color="$page->status === 'published' ? 'success' : 'gray'" dot>{{ ucfirst($page->status) }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $page->updated_at?->diffForHumans() ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                                x-on:click="edit({{ Illuminate\Support\Js::from(['id' => $page->id, 'title' => $page->title, 'slug' => $page->slug, 'status' => $page->status, 'meta_description' => (string) $page->meta_description, 'content' => (string) $page->content]) }})">Edit</x-ui.button>
                            <form method="POST" action="{{ route('admin.pages.delete', $page->id) }}" onsubmit="return confirm('Delete this page? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" variant="ghost" size="sm" icon="trash">Delete</x-ui.button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5"><x-ui.empty-state icon="document-text" title="No pages" description="Create your first content page." /></td></tr>
            @endforelse
        </x-ui.table>

        {{-- Add / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500/60" x-on:click="open = false"></div>
            <div class="relative w-full max-w-2xl pp-card p-6 max-h-[90vh] overflow-y-auto">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-neutral-900" x-text="editingId ? 'Edit page' : 'New page'"></h3>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <form method="POST" action="{{ route('admin.pages.save') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="id" :value="editingId" />
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-ui.input label="Title" name="title" x-model="form.title" x-on:blur="slugify()" placeholder="About us" :error="$errors->first('title')" />
                        <x-ui.input label="Slug" name="slug" x-model="form.slug" placeholder="about-us" :error="$errors->first('slug')" />
                    </div>
                    <x-ui.select label="Status" name="status" x-model="form.status" :error="$errors->first('status')">
                        <option value="published">Published</option>
                        <option value="draft">Draft</option>
                    </x-ui.select>
                    <x-ui.input label="Meta description (SEO)" name="meta_description" x-model="form.meta_description" placeholder="A short summary for search engines." :error="$errors->first('meta_description')" />
                    <x-ui.textarea label="Content (HTML / Markdown body)" name="content" x-model="form.content" :rows="12" class="font-mono text-sm" placeholder="<h2>Welcome</h2><p>…</p>" :error="$errors->first('content')" />
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                        <x-ui.button type="submit" x-text="editingId ? 'Save changes' : 'Create page'"></x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
