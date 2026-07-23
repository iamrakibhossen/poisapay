<x-layouts.app :title="'Notification preferences'">
    @php
        $channels = [
            ['key' => 'in_app', 'label' => 'In-app'],
            ['key' => 'email', 'label' => 'Email'],
            ['key' => 'push', 'label' => 'Push'],
            ['key' => 'sms', 'label' => 'SMS'],
        ];
    @endphp

    <div class="mx-auto max-w-3xl space-y-6">
        <x-ui.page-header title="Notification preferences" subtitle="Choose how you'd like to hear from us for each type of notification.">
            <x-slot:actions>
                <x-ui.button href="{{ route('notifications') }}" variant="ghost" size="sm" icon="arrow-left">Back to notifications</x-ui.button>
            </x-slot:actions>
        </x-ui.page-header>

        <x-ui.card>
            <form method="POST" action="{{ route('notifications.preferences.update') }}" class="space-y-4">
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
                        <div class="col-span-2 flex items-center gap-3 sm:col-span-1">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full {{ $categoryMeta[$cat]['tint'] }}">
                                <x-dynamic-component :component="'heroicon-o-'.$categoryMeta[$cat]['icon']" class="h-5 w-5" />
                            </span>
                            <div>
                                <p class="text-sm font-semibold text-neutral-900">{{ $label }}</p>
                                @if ($cat === 'security')
                                    <p class="text-xs text-neutral-400">Always on for your protection.</p>
                                @endif
                            </div>
                        </div>

                        @foreach ($channels as $ch)
                            @php
                                $locked = $cat === 'security' && in_array($ch['key'], ['in_app', 'email'], true);
                                $checked = (bool) old("prefs.$cat.{$ch['key']}", $prefs[$cat][$ch['key']]);
                                $checked = $locked ? true : $checked;
                            @endphp
                            <label class="flex items-center justify-between gap-2 sm:justify-center">
                                <span class="text-sm text-neutral-600 sm:hidden">{{ $ch['label'] }}</span>
                                {{-- Ensure an unchecked box still submits a value so validation sees a boolean. --}}
                                <input type="hidden" name="prefs[{{ $cat }}][{{ $ch['key'] }}]" value="0" />
                                <input type="checkbox" name="prefs[{{ $cat }}][{{ $ch['key'] }}]" value="1"
                                    @checked($checked) @disabled($locked)
                                    class="peer sr-only" />
                                {{-- iOS-style switch driven by the peer checkbox. --}}
                                <span aria-hidden="true"
                                    class="relative h-5 w-9 shrink-0 rounded-full bg-neutral-200 transition-colors peer-checked:bg-brand-500 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500/40 peer-focus-visible:ring-offset-1 peer-disabled:opacity-60 after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-4"></span>
                                {{-- Locked security channels are disabled, so also submit them explicitly. --}}
                                @if ($locked)<input type="hidden" name="prefs[{{ $cat }}][{{ $ch['key'] }}]" value="1" />@endif
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
</x-layouts.app>
