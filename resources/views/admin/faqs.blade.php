<x-layouts.admin :title="'FAQs'">
    {{-- Alpine is light UI only: modal open/close + prefill for edit. The form POSTs traditionally. --}}
    <div x-data="{
            open: {{ $errors->any() ? 'true' : 'false' }},
            editingId: '{{ old('id') }}',
            form: {
                question: @js(old('question', '')),
                answer: @js(old('answer', '')),
                group: @js(old('group', '')),
                sort_order: {{ (int) old('sort_order', 0) }},
                status: '{{ old('status', 'published') }}',
                show_on_homepage: {{ old('show_on_homepage', true) ? 'true' : 'false' }},
            },
            create() { this.editingId = ''; this.form = { question: '', answer: '', group: '', sort_order: 0, status: 'published', show_on_homepage: true }; this.open = true; },
            edit(f) { this.editingId = f.id; this.form = { question: f.question, answer: f.answer, group: f.group, sort_order: f.sort_order, status: f.status, show_on_homepage: f.show_on_homepage }; this.open = true; },
        }" class="space-y-6">
        <x-ui.page-header title="FAQs" subtitle="Questions answered on the public help page.">
            <x-slot:actions>
                <x-ui.button x-on:click="create()" icon="plus" size="sm">New FAQ</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.table :headers="['Question', 'Group', 'Homepage', 'Order', 'Status', '']">
            @forelse ($faqs as $faq)
                <tr class="border-b border-gray-200 hover:bg-gray-100">
                    <td class="px-4 py-3">
                        <p class="text-sm font-semibold text-neutral-900">{{ $faq->question }}</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-neutral-600">{{ $faq->group ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($faq->show_on_homepage)
                            <x-heroicon-s-check-circle class="h-5 w-5 text-green-500" />
                        @else
                            <x-heroicon-o-minus-circle class="h-5 w-5 text-neutral-300" />
                        @endif
                    </td>
                    <td class="px-4 py-3 font-mono text-sm text-neutral-600">{{ $faq->sort_order }}</td>
                    <td class="px-4 py-3">
                        <x-ui.badge :color="$faq->status === 'published' ? 'success' : 'gray'" dot>{{ ucfirst($faq->status) }}</x-ui.badge>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                                x-on:click="edit({{ Illuminate\Support\Js::from(['id' => $faq->id, 'question' => $faq->question, 'answer' => $faq->answer, 'group' => (string) $faq->group, 'sort_order' => (int) $faq->sort_order, 'status' => $faq->status, 'show_on_homepage' => (bool) $faq->show_on_homepage]) }})">Edit</x-ui.button>
                            <form method="POST" action="{{ route('admin.faqs.delete', $faq->id) }}" onsubmit="return confirm('Delete this FAQ? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <x-ui.button type="submit" variant="ghost" size="sm" icon="trash">Delete</x-ui.button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-ui.empty-state icon="question-mark-circle" title="No FAQs" description="Add your first question and answer." /></td></tr>
            @endforelse
        </x-ui.table>

        {{-- Add / edit modal --}}
        <div x-show="open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="fixed inset-0 bg-gray-500/60" x-on:click="open = false"></div>
            <div class="relative w-full max-w-2xl pp-card p-6 max-h-[90vh] overflow-y-auto">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-neutral-900" x-text="editingId ? 'Edit FAQ' : 'New FAQ'"></h3>
                    <button type="button" x-on:click="open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                </div>
                <form method="POST" action="{{ route('admin.faqs.save') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="id" :value="editingId" />
                    <x-ui.input label="Question" name="question" x-model="form.question" placeholder="How do I make a deposit?" :error="$errors->first('question')" />
                    <x-ui.textarea label="Answer" name="answer" x-model="form.answer" :rows="5" placeholder="Explain the answer clearly." :error="$errors->first('answer')" />
                    <div class="grid gap-4 sm:grid-cols-3">
                        <x-ui.input label="Group" name="group" x-model="form.group" placeholder="Deposits" :error="$errors->first('group')" />
                        <x-ui.input label="Sort order" type="number" name="sort_order" x-model="form.sort_order" placeholder="0" :error="$errors->first('sort_order')" />
                        <x-ui.select label="Status" name="status" x-model="form.status" :error="$errors->first('status')">
                            <option value="published">Published</option>
                            <option value="draft">Draft</option>
                        </x-ui.select>
                    </div>
                    <x-ui.checkbox name="show_on_homepage" value="1" x-model="form.show_on_homepage" label="Show on homepage" />
                    <div class="flex justify-end gap-2 pt-2">
                        <x-ui.button type="button" variant="secondary" x-on:click="open = false">Cancel</x-ui.button>
                        <x-ui.button type="submit" x-text="editingId ? 'Save changes' : 'Create FAQ'"></x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.admin>
