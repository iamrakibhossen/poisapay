<x-layouts.admin :title="__('Messaging')">
    @php
        $canManage = auth('admin')->user()?->can('manage-settings') || auth('admin')->user()?->hasRole('super-admin');
        $categoryColor = fn (string $c) => match ($c) {
            'security' => 'danger',
            'money' => 'success',
            'marketing' => 'indigo',
            default => 'info',
        };
        // Which modal (if any) submitted and failed validation — reopen it.
        $failedForm = $errors->any() ? old('_form') : null;
    @endphp

    {{-- Alpine is light UI only: modal open/close + prefill for edit. Both forms POST traditionally. --}}
    <div x-data="{
            template: {
                open: {{ $failedForm === 'template' ? 'true' : 'false' }},
                editingId: '{{ old('id') }}',
                form: {
                    key: @js(old('key', '')),
                    locale: @js(old('locale', 'en')),
                    name: @js(old('name', '')),
                    category: @js(old('category', 'product')),
                    channels: @js(old('channels', ['in_app'])),
                    subject: @js(old('subject', '')),
                    body: @js(old('body', '')),
                    is_active: {{ old('_form') === 'template' ? (old('is_active') ? 'true' : 'false') : 'true' }},
                },
                create() {
                    this.editingId = '';
                    this.form = { key: '', locale: 'en', name: '', category: 'product', channels: ['in_app'], subject: '', body: '', is_active: true };
                    this.open = true;
                },
                edit(t) {
                    this.editingId = t.id;
                    this.form = { key: t.key, locale: t.locale, name: t.name, category: t.category, channels: t.channels ?? [], subject: t.subject ?? '', body: t.body ?? '', is_active: t.is_active };
                    this.open = true;
                },
            },
            announcement: {
                open: {{ $failedForm === 'announcement' ? 'true' : 'false' }},
                form: {
                    annTitle: @js(old('annTitle', '')),
                    annBody: @js(old('annBody', '')),
                    annSegment: @js(old('annSegment', 'all')),
                    annCategory: @js(old('annCategory', 'product')),
                    annChannels: @js(old('annChannels', ['in_app'])),
                },
                openCompose() {
                    this.form = { annTitle: '', annBody: '', annSegment: 'all', annCategory: 'product', annChannels: ['in_app'] };
                    this.open = true;
                },
            },
        }" class="space-y-6">
        <x-ui.page-header :title="__('Messaging')" :subtitle="__('Notification templates and broadcast announcements.')" />

        {{-- Stat cards --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat-card :label="__('Templates')" :value="number_format($stats['templates'])" icon="document-text" accent="brand" />
            <x-ui.stat-card :label="__('Active templates')" :value="number_format($stats['activeTemplates'])" icon="check-badge" accent="emerald" />
            <x-ui.stat-card :label="__('Announcements sent')" :value="number_format($stats['announcements'])" icon="megaphone" accent="amber" />
            <x-ui.stat-card :label="__('Recipients reached')" :value="number_format($stats['recipients'])" icon="user-group" accent="brand" />
        </div>

        {{-- DollarHub shell: sticky vertical section nav + content panel. --}}
        <div class="grid gap-6 lg:grid-cols-5">
            {{-- Vertical section navigation --}}
            <nav class="flex gap-1 overflow-x-auto rounded-xl border border-gray-200 bg-white p-2 lg:sticky lg:top-6 lg:col-span-1 lg:flex-col lg:self-start lg:overflow-visible">
                @foreach (['templates' => ['label' => __('Templates'), 'icon' => 'document-text'], 'announcements' => ['label' => __('Announcements'), 'icon' => 'megaphone']] as $key => $meta)
                    <a
                        href="{{ route('admin.messaging', ['tab' => $key]) }}"
                        @class([
                            'flex shrink-0 items-center gap-2.5 rounded-lg px-3.5 py-2.5 text-sm font-medium transition lg:w-full',
                            'bg-brand-500 text-ink-900' => $tab === $key,
                            'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900' => $tab !== $key,
                        ])
                    >
                        <x-dynamic-component :component="'heroicon-o-'.$meta['icon']" class="h-5 w-5 shrink-0" />
                        <span>{{ $meta['label'] }}</span>
                    </a>
                @endforeach
            </nav>

            {{-- Active section content --}}
            <div class="space-y-6 lg:col-span-4">

                {{-- Section actions --}}
                @if ($canManage)
                    <div class="flex flex-wrap justify-end gap-2">
                        @if ($tab === 'templates')
                            <x-ui.button x-on:click="template.create()" icon="plus" size="sm">{{ __('New template') }}</x-ui.button>
                        @elseif ($tab === 'announcements')
                            <x-ui.button x-on:click="announcement.openCompose()" icon="megaphone" size="sm">{{ __('Compose') }}</x-ui.button>
                        @endif
                    </div>
                @endif

                {{-- TEMPLATES TAB --}}
                @if ($tab === 'templates')
                    <x-ui.table :headers="[__('Key'), __('Name'), __('Category'), __('Channels'), __('Locale'), __('Status'), '']">
                        @forelse ($templates as $t)
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="px-3 py-3 font-mono text-xs text-neutral-600">{{ $t->key }}</td>
                                <td class="px-3 py-3 text-sm font-medium text-neutral-900">{{ $t->name }}</td>
                                <td class="px-3 py-3"><x-ui.badge :color="$categoryColor($t->category)">{{ ucfirst($t->category) }}</x-ui.badge></td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap items-center gap-1">
                                        @forelse ($t->channels ?? [] as $ch)
                                            <x-ui.badge color="gray">{{ $ch === 'in_app' ? __('In-app') : __('Email') }}</x-ui.badge>
                                        @empty
                                            <span class="text-xs text-neutral-400">—</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-sm uppercase text-neutral-600">{{ $t->locale }}</td>
                                <td class="px-3 py-3">
                                    @if ($canManage)
                                        <form method="POST" action="{{ route('admin.messaging.template.toggle', $t->id) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex">
                                                <x-ui.badge :color="$t->is_active ? 'success' : 'gray'" dot>{{ $t->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                                            </button>
                                        </form>
                                    @else
                                        <x-ui.badge :color="$t->is_active ? 'success' : 'gray'" dot>{{ $t->is_active ? __('Active') : __('Inactive') }}</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-3 py-3 text-right">
                                    @if ($canManage)
                                        <x-ui.button variant="secondary" size="sm" icon="pencil-square"
                                            x-on:click="template.edit({{ Illuminate\Support\Js::from(['id' => $t->id, 'key' => $t->key, 'locale' => $t->locale, 'name' => $t->name, 'category' => $t->category, 'channels' => $t->channels ?? [], 'subject' => (string) $t->subject, 'body' => $t->body, 'is_active' => (bool) $t->is_active]) }})">{{ __('Edit') }}</x-ui.button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><x-ui.empty-state icon="document-text" :title="__('No templates')" :description="__('Create a notification template to control how events are delivered.')" /></td></tr>
                        @endforelse
                    </x-ui.table>
                @endif

                {{-- ANNOUNCEMENTS TAB --}}
                @if ($tab === 'announcements')
                    <x-ui.table :headers="[__('Title'), __('Segment'), __('Channels'), __('Recipients'), __('Sent by'), __('Sent')]">
                        @forelse ($announcements as $a)
                            <tr class="border-b border-gray-200 hover:bg-gray-100">
                                <td class="px-3 py-3 text-sm font-medium text-neutral-900">{{ $a->title }}</td>
                                <td class="px-3 py-3">
                                    @php $segmentLabel = ['kyc_full' => __('Full-KYC'), 'merchants' => __('Merchants')][$a->segment] ?? __('All users'); @endphp
                                    <x-ui.badge color="indigo">{{ $segmentLabel }}</x-ui.badge>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-wrap items-center gap-1">
                                        @foreach ($a->channels ?? [] as $ch)
                                            <x-ui.badge color="gray">{{ $ch === 'in_app' ? __('In-app') : __('Email') }}</x-ui.badge>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="px-3 py-3 tabular text-sm font-semibold text-neutral-900">{{ number_format($a->recipients) }}</td>
                                <td class="px-3 py-3 text-sm text-neutral-600">{{ $a->sender?->name ?? '—' }}</td>
                                <td class="px-3 py-3 text-xs text-neutral-500">{{ $a->sent_at?->diffForHumans() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-ui.empty-state icon="megaphone" :title="__('No announcements')" :description="__('Compose an announcement to broadcast a message to a user segment.')" /></td></tr>
                        @endforelse
                    </x-ui.table>

                    {{ $announcements->links() }}
                @endif

            </div> {{-- /content panel --}}
        </div> {{-- /messaging grid --}}

        {{-- Template create / edit modal --}}
        @if ($canManage)
            <div x-show="template.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="template.open = false"></div>
                <div class="relative w-full max-w-lg pp-card max-h-[90vh] overflow-y-auto p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900" x-text="template.editingId ? 'Edit template' : 'New template'"></h3>
                        <button type="button" x-on:click="template.open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <form method="POST" action="{{ route('admin.messaging.template.save') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_form" value="template" />
                        <input type="hidden" name="id" :value="template.editingId" />
                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input :label="__('Key')" name="key" x-model="template.form.key" x-bind:disabled="!!template.editingId" placeholder="deposit.credited" :error="$errors->first('key')" />
                            <x-ui.input :label="__('Locale')" name="locale" x-model="template.form.locale" placeholder="en" :error="$errors->first('locale')" />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.input :label="__('Name')" name="name" x-model="template.form.name" :placeholder="__('Deposit credited')" :error="$errors->first('name')" />
                            <x-ui.select :label="__('Category')" name="category" x-model="template.form.category" :error="$errors->first('category')">
                                <option value="security">{{ __('Security') }}</option>
                                <option value="money">{{ __('Money') }}</option>
                                <option value="marketing">{{ __('Marketing') }}</option>
                                <option value="product">{{ __('Product') }}</option>
                            </x-ui.select>
                        </div>

                        <div>
                            <label class="pp-label">{{ __('Channels') }}</label>
                            <div class="flex flex-wrap gap-4">
                                <label class="flex items-center gap-2 text-sm text-neutral-700"><input type="checkbox" name="channels[]" value="in_app" x-model="template.form.channels" class="rounded border-neutral-300 text-brand-500 focus:ring-brand-500"> {{ __('In-app') }}</label>
                                <label class="flex items-center gap-2 text-sm text-neutral-700"><input type="checkbox" name="channels[]" value="email" x-model="template.form.channels" class="rounded border-neutral-300 text-brand-500 focus:ring-brand-500"> {{ __('Email') }}</label>
                            </div>
                            @error('channels') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <x-ui.input :label="__('Subject (optional)')" name="subject" x-model="template.form.subject" :placeholder="__('Your deposit is in')" :error="$errors->first('subject')" />

                        <x-ui.textarea :label="__('Body')" name="body" x-model="template.form.body" :rows="5" class="[&_textarea]:font-mono [&_textarea]:text-sm"
                            :placeholder="__('Hi {{name}}, your deposit of {{amount}} has been credited.')"
                            :hint="__('Use {{token}} placeholders (e.g. {{name}}) — substituted at send time.')"
                            :error="$errors->first('body')" />

                        <label class="flex items-center gap-2 text-sm text-neutral-700"><input type="checkbox" name="is_active" value="1" x-model="template.form.is_active" class="rounded border-neutral-300 text-brand-500 focus:ring-brand-500"> {{ __('Active') }}</label>

                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button type="button" variant="secondary" x-on:click="template.open = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="submit" x-text="template.editingId ? 'Save changes' : 'Create template'"></x-ui.button>
                        </div>
                    </form>
                </div>
            </div>

            {{-- Announcement compose modal --}}
            <div x-show="announcement.open" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-gray-500/60" x-on:click="announcement.open = false"></div>
                <div class="relative w-full max-w-lg pp-card max-h-[90vh] overflow-y-auto p-6">
                    <div class="mb-4 flex items-start justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">{{ __('Compose announcement') }}</h3>
                        <button type="button" x-on:click="announcement.open = false" class="rounded-lg p-1 text-neutral-400 hover:bg-neutral-100"><x-heroicon-o-x-mark class="h-5 w-5" /></button>
                    </div>
                    <p class="mb-4 text-sm text-neutral-500">{{ __('Broadcast a one-off message to a user segment. This is delivered to the in-app bell (and email when selected).') }}</p>
                    <form method="POST" action="{{ route('admin.messaging.announcement.send') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_form" value="announcement" />
                        <x-ui.input :label="__('Title')" name="annTitle" x-model="announcement.form.annTitle" :placeholder="__('Scheduled maintenance')" :error="$errors->first('annTitle')" />

                        <x-ui.textarea :label="__('Body')" name="annBody" x-model="announcement.form.annBody" :rows="4" :placeholder="__('We\'ll be performing maintenance tonight…')" :error="$errors->first('annBody')" />

                        <div class="grid gap-4 sm:grid-cols-2">
                            <x-ui.select :label="__('Segment')" name="annSegment" x-model="announcement.form.annSegment" :error="$errors->first('annSegment')">
                                <option value="all">{{ __('All users') }}</option>
                                <option value="kyc_full">{{ __('Full-KYC users') }}</option>
                                <option value="merchants">{{ __('Merchants') }}</option>
                            </x-ui.select>
                            <x-ui.select :label="__('Category')" name="annCategory" x-model="announcement.form.annCategory" :error="$errors->first('annCategory')">
                                <option value="security">{{ __('Security') }}</option>
                                <option value="money">{{ __('Money') }}</option>
                                <option value="marketing">{{ __('Marketing') }}</option>
                                <option value="product">{{ __('Product') }}</option>
                            </x-ui.select>
                        </div>

                        <div>
                            <label class="pp-label">{{ __('Channels') }}</label>
                            <div class="flex flex-wrap gap-4">
                                <label class="flex items-center gap-2 text-sm text-neutral-700"><input type="checkbox" name="annChannels[]" value="in_app" x-model="announcement.form.annChannels" class="rounded border-neutral-300 text-brand-500 focus:ring-brand-500"> {{ __('In-app') }}</label>
                                <label class="flex items-center gap-2 text-sm text-neutral-700"><input type="checkbox" name="annChannels[]" value="email" x-model="announcement.form.annChannels" class="rounded border-neutral-300 text-brand-500 focus:ring-brand-500"> {{ __('Email') }}</label>
                            </div>
                            @error('annChannels') <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="flex justify-end gap-2 pt-2">
                            <x-ui.button type="button" variant="secondary" x-on:click="announcement.open = false">{{ __('Cancel') }}</x-ui.button>
                            <x-ui.button type="submit" icon="megaphone">{{ __('Send announcement') }}</x-ui.button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-layouts.admin>
