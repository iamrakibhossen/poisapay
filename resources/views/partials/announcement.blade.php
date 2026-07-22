@if (getSetting('header_announcement_enabled'))
    @php
        $announcementText = trim((string) getSetting('header_announcement_text', ''));
        $announcementLink = trim((string) getSetting('header_announcement_link', ''));
        $announcementKey = 'pp-announcement-'.md5($announcementText.'|'.$announcementLink);
    @endphp
    @if ($announcementText !== '')
        <div
            x-data="{ show: localStorage.getItem('{{ $announcementKey }}') !== '1' }"
            x-show="show"
            x-cloak
            class="relative bg-brand-500 text-ink-900"
        >
            <div class="mx-auto flex max-w-7xl items-center justify-center gap-3 px-4 py-2 text-center text-sm font-medium sm:px-6 lg:px-8">
                @if ($announcementLink !== '')
                    <a href="{{ $announcementLink }}" class="hover:underline">{{ $announcementText }}</a>
                @else
                    <span>{{ $announcementText }}</span>
                @endif
            </div>
            <button
                type="button"
                @click="show = false; localStorage.setItem('{{ $announcementKey }}', '1')"
                class="absolute inset-y-0 right-2 my-auto grid h-6 w-6 place-items-center rounded-md text-ink-900/70 hover:bg-black/10 hover:text-ink-900"
                aria-label="Dismiss announcement"
            >
                <x-heroicon-o-x-mark class="h-4 w-4" />
            </button>
        </div>
    @endif
@endif
