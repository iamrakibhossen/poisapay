<x-layouts.app :title="'Notifications'">
    @php
        $channels = [
            ['key' => 'in_app', 'label' => 'In-app'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'push', 'label' => 'Push'],
            ['key' => 'sms', 'label' => 'SMS'],
        ];
        $badgeClasses = [
            'security' => 'bg-red-100 text-red-700',
            'money' => 'bg-emerald-100 text-emerald-700',
            'product' => 'bg-sky-100 text-sky-700',
            'marketing' => 'bg-indigo-100 text-indigo-700',
        ];
        // Open the preferences tab automatically if validation failed there.
        $initialTab = $errors->any() ? 'preferences' : 'activity';
    @endphp

    {{-- Alpine here is light UI only: tab switching. Everything is server-rendered. --}}
    <div x-data="{ tab: '{{ $initialTab }}' }" class="mx-auto max-w-3xl space-y-6">
        <x-ui.page-header title="Notifications" subtitle="Your recent account activity and delivery preferences.">
            <x-slot:actions>
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}" x-show="tab === 'activity'">
                        @csrf
                        <x-ui.button type="submit" variant="secondary" size="sm" icon="check">Mark all as read</x-ui.button>
                    </form>
                @endif
            </x-slot:actions>
        </x-ui.page-header>

        {{-- Tabs --}}
        <div class="flex items-center gap-1 rounded-xl border border-neutral-200 bg-white p-1 text-sm font-medium">
            <button type="button" x-on:click="tab = 'activity'"
                class="flex-1 rounded-lg px-4 py-2 transition"
                :class="tab === 'activity' ? 'bg-brand-500 text-white' : 'text-neutral-500 hover:bg-neutral-50'">
                Activity
                @if ($unreadCount > 0)
                    <span class="ml-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 text-[11px] font-semibold text-white">{{ $unreadCount }}</span>
                @endif
            </button>
            <button type="button" x-on:click="tab = 'preferences'"
                class="flex-1 rounded-lg px-4 py-2 transition"
                :class="tab === 'preferences' ? 'bg-brand-500 text-white' : 'text-neutral-500 hover:bg-neutral-50'">
                Preferences
            </button>
        </div>

        {{-- Activity --}}
        <div x-show="tab === 'activity'">
            @if ($notifications->isEmpty())
                <x-ui.card>
                    <x-ui.empty-state icon="bell" title="No notifications yet"
                        description="Account activity, security alerts and product updates will show up here." />
                </x-ui.card>
            @else
                <div class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
                    @foreach ($notifications as $note)
                        <div class="flex items-start gap-3 border-b border-neutral-100 px-4 py-4 last:border-b-0 {{ $note['is_unread'] ? 'bg-brand-50' : '' }}">
                            {{-- Unread dot --}}
                            <span class="mt-1.5 flex h-2 w-2 shrink-0 items-center justify-center">
                                @if ($note['is_unread'])<span class="h-2 w-2 rounded-full bg-brand-500"></span>@endif
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="mb-1 flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $badgeClasses[$note['category']] ?? 'bg-gray-100 text-gray-600' }}">{{ ucfirst($note['category']) }}</span>
                                    <span class="text-xs text-neutral-400">{{ $note['created'] }}</span>
                                </div>

                                @if ($note['url'])
                                    <a href="{{ $note['url'] }}" class="block font-semibold text-neutral-900 hover:text-brand-600">{{ $note['title'] }}</a>
                                @else
                                    <p class="font-semibold text-neutral-900">{{ $note['title'] }}</p>
                                @endif

                                @if ($note['body'])
                                    <p class="mt-0.5 text-sm text-neutral-600">{{ $note['body'] }}</p>
                                @endif
                            </div>

                            {{-- Per-item mark read --}}
                            @if ($note['is_unread'])
                                <form method="POST" action="{{ route('notifications.read', $note['id']) }}">
                                    @csrf
                                    <button type="submit"
                                        class="shrink-0 rounded-lg p-1.5 text-neutral-400 hover:bg-neutral-100 hover:text-brand-600"
                                        title="Mark as read" aria-label="Mark as read">
                                        <x-heroicon-o-check class="h-5 w-5" />
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Preferences --}}
        <div x-show="tab === 'preferences'" x-cloak>
            <x-ui.card title="Delivery preferences" subtitle="Choose how you'd like to hear from us for each type of notification.">
                <form method="POST" action="{{ route('notifications.preferences') }}" class="space-y-4">
                    @csrf @method('PUT')

                    {{-- Header row (channels) --}}
                    <div class="hidden grid-cols-[1fr_repeat(4,4rem)] items-center gap-2 border-b border-neutral-100 pb-3 text-xs font-semibold uppercase tracking-wide text-neutral-400 sm:grid">
                        <span>Category</span>
                        <span class="text-center">In-app</span>
                        <span class="text-center">Email</span>
                        <span class="text-center">Push</span>
                        <span class="text-center">SMS</span>
                    </div>

                    @foreach ($categories as $cat => $label)
                        <div class="grid grid-cols-2 items-center gap-3 rounded-xl border border-neutral-200 p-4 sm:grid-cols-[1fr_repeat(4,4rem)] sm:gap-2 sm:border-0 sm:border-b sm:border-neutral-100 sm:rounded-none sm:p-0 sm:pb-4 sm:last:border-b-0 sm:last:pb-0">
                            <div class="col-span-2 sm:col-span-1">
                                <p class="text-sm font-semibold text-neutral-900">{{ $label }}</p>
                                @if ($cat === 'security')
                                    <p class="text-xs text-neutral-400">Always on for your protection.</p>
                                @endif
                            </div>

                            @foreach ($channels as $ch)
                                @php
                                    $locked = $cat === 'security' && in_array($ch['key'], ['in_app', 'email'], true);
                                    $checked = (bool) old("prefs.$cat.{$ch['key']}", $prefs[$cat][$ch['key']]);
                                    $checked = $locked ? true : $checked;
                                @endphp
                                <label class="flex items-center gap-2 sm:justify-center">
                                    {{-- Ensure an unchecked box still submits a value so validation sees a boolean. --}}
                                    <input type="hidden" name="prefs[{{ $cat }}][{{ $ch['key'] }}]" value="0" />
                                    <input type="checkbox" name="prefs[{{ $cat }}][{{ $ch['key'] }}]" value="1"
                                        @checked($checked) @disabled($locked)
                                        class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 disabled:cursor-not-allowed disabled:opacity-60" />
                                    {{-- Locked security channels are disabled, so also submit them explicitly. --}}
                                    @if ($locked)<input type="hidden" name="prefs[{{ $cat }}][{{ $ch['key'] }}]" value="1" />@endif
                                    <span class="text-sm text-neutral-600 sm:hidden">{{ $ch['label'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach

                    @error('prefs')<p class="text-xs text-red-600">{{ $message }}</p>@enderror

                    <div class="flex justify-end pt-2">
                        <x-ui.button type="submit" icon="check">Save preferences</x-ui.button>
                    </div>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-layouts.app>
