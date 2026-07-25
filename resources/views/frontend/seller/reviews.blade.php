<x-layouts.app :title="__('Reviews')">
    <div class="mx-auto mt-6 max-w-2xl space-y-5">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight text-neutral-900">{{ __('Reviews') }}</h1>
                <p class="mt-1 text-sm text-neutral-500">{{ __('Feedback from verified buyers.') }}</p>
            </div>
            <div class="text-right">
                <p class="text-2xl font-bold text-neutral-900">{{ $summary['avg'] }} <span class="text-amber-500">★</span></p>
                <p class="text-xs text-neutral-500">{{ number_format($summary['count']) }} {{ __('reviews') }}</p>
            </div>
        </div>

        @if (session('success'))
            <x-ui.alert type="success">{{ session('success') }}</x-ui.alert>
        @endif

        @if (count($reviews))
        <div class="space-y-3">
            @foreach ($reviews as $r)
                <x-ui.card>
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="text-sm font-semibold text-neutral-900">{{ $r['buyer'] }}</p>
                            <p class="text-xs text-neutral-400">{{ $r['product'] }} · {{ $r['date'] }}</p>
                        </div>
                        <div class="text-amber-500 text-sm">{!! str_repeat('★', $r['rating']) . str_repeat('☆', 5 - $r['rating']) !!}</div>
                    </div>
                    @if (! empty($r['title']))<p class="mt-2 text-sm font-semibold text-neutral-900">{{ $r['title'] }}</p>@endif
                    @if (! empty($r['body']))<p class="mt-1 text-sm leading-relaxed text-neutral-700">{{ $r['body'] }}</p>@endif

                    @if ($r['reply'])
                        <div class="mt-3 rounded-xl border border-neutral-200 bg-neutral-50/60 p-3">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('Your reply') }}</p>
                            <p class="mt-1 text-sm text-neutral-600">{{ $r['reply'] }}</p>
                        </div>
                    @else
                        <form method="POST" action="{{ route('sell.reviews.reply', ['id' => $r['id']]) }}" class="mt-3" x-data="{ open: false }">
                            @csrf
                            <button type="button" x-show="! open" x-on:click="open = true" class="text-xs font-medium text-brand-600 hover:text-brand-700">{{ __('Reply') }}</button>
                            <div x-show="open" x-cloak class="flex gap-2">
                                <input name="reply" required placeholder="{{ __('Write a reply…') }}" class="flex-1 rounded-lg border border-neutral-200 px-3 py-2 text-sm focus:border-brand-500 focus:ring-1 focus:ring-brand-500" />
                                <x-ui.button type="submit" size="sm">{{ __('Send') }}</x-ui.button>
                            </div>
                        </form>
                    @endif
                </x-ui.card>
            @endforeach
        </div>
        @else
            <div class="pp-card">
                <x-ui.empty-state icon="star" :title="__('No reviews yet')" :description="__('Reviews from verified buyers will appear here after they purchase.')" />
            </div>
        @endif
    </div>
</x-layouts.app>
