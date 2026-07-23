@props(['title' => 'Dashboard'])

{{-- DollarHub admin topbar — 1:1: sticky white bar, sidebar toggle, title, bell + user-circle. --}}
<div class="sticky top-0 z-10 w-full border-b border-gray-200 bg-white px-4 py-2 lg:px-8">
    <div class="flex h-12 w-full items-center gap-4 lg:gap-5">
        <button @click="sidebarOpen = !sidebarOpen" class="cursor-pointer text-gray-600 hover:text-gray-900" aria-label="Toggle sidebar">
            <x-heroicon-o-bars-3-bottom-left class="h-6 w-6" />
        </button>

        <h1 class="text-lg font-medium">{{ $title }}</h1>

        <div class="ml-auto flex items-center justify-center gap-4">
            {{-- Notifications (real DB notifications) --}}
            @php
                $admin = auth('admin')->user();
                $recent = $admin?->notifications()->latest()->limit(6)->get() ?? collect();
                $unread = $admin?->unreadNotifications()->count() ?? 0;
            @endphp
            <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                <button type="button" @click="open = !open"
                    class="relative flex h-10 w-10 cursor-pointer items-center justify-center rounded-full text-gray-600 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-blue-300"
                    aria-label="Notifications">
                    <x-heroicon-o-bell class="h-7 w-7" />
                    @if ($unread > 0)
                        <span class="absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1 text-xs font-semibold text-white">{{ $unread > 99 ? '99+' : $unread }}</span>
                    @endif
                </button>
                <div x-show="open" x-cloak @click.outside="open = false" x-transition.origin.top.right
                    class="absolute right-0 z-50 mt-2 w-80 max-w-[90vw] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg">
                    <div class="flex items-center justify-between border-b border-gray-200 px-4 py-3">
                        <span class="font-semibold text-gray-800">Notifications</span>
                        @if ($unread > 0)
                            <form method="POST" action="{{ route('admin.notifications.read-all') }}">
                                @csrf
                                <button type="submit" class="text-xs font-medium text-blue-600 hover:text-blue-800">Mark all read</button>
                            </form>
                        @endif
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        @forelse ($recent as $n)
                            {{-- POST so the click marks it read (updating the unread count) then
                                 follows its deep link — same behaviour as the full feed. --}}
                            <form method="POST" action="{{ route('admin.notifications.read', $n->id) }}" class="block">
                                @csrf
                                <button type="submit"
                                    class="flex w-full items-start gap-3 border-b border-gray-100 px-4 py-3 text-left hover:bg-gray-50 {{ is_null($n->read_at) ? 'bg-blue-50/50' : '' }}">
                                    <span class="mt-0.5 shrink-0 rounded-full bg-gray-100 p-2 text-gray-500"><x-heroicon-o-bell class="h-4 w-4" /></span>
                                    <span class="block min-w-0 flex-1">
                                        <span class="block truncate text-sm font-medium text-gray-800">{{ $n->data['title'] ?? 'Notification' }}</span>
                                        <span class="block truncate text-xs text-gray-500">{{ $n->data['body'] ?? '' }}</span>
                                        <span class="mt-0.5 block text-[11px] text-gray-400">{{ $n->created_at->diffForHumans() }}</span>
                                    </span>
                                    @if (is_null($n->read_at))
                                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-blue-500"></span>
                                    @endif
                                </button>
                            </form>
                        @empty
                            <div class="px-4 py-10 text-center text-sm text-gray-500">No notifications yet</div>
                        @endforelse
                    </div>
                    <a href="{{ route('admin.notifications') }}" class="block border-t border-gray-200 px-4 py-3 text-center text-sm font-medium text-gray-600 hover:bg-gray-50">View all</a>
                </div>
            </div>

            {{-- User dropdown --}}
            @auth('admin')
            <div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
                <button type="button" @click="open = !open"
                    class="flex cursor-pointer items-center justify-center rounded-full focus:outline-none focus:ring-2 focus:ring-blue-200"
                    aria-label="Account">
                    <x-ui.avatar :name="$admin?->name ?? ''" size="sm" />
                </button>
                <div x-show="open" x-cloak x-transition @click.outside="open = false"
                    class="absolute right-0 mt-2 w-56 origin-top-right overflow-hidden rounded-xl border border-gray-200 bg-white py-1 shadow-lg">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <x-ui.user :user="$admin" size="sm" />
                    </div>
                    <a href="{{ route('home') }}" class="flex items-center gap-2 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100"><x-heroicon-o-globe-alt class="h-5 w-5" /> View site</a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-3 text-sm font-medium text-gray-700 hover:bg-gray-100"><x-heroicon-o-arrow-right-start-on-rectangle class="h-5 w-5" /> Logout</button>
                    </form>
                </div>
            </div>
            @endauth
        </div>
    </div>
</div>
