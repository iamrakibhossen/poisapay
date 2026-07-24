{{-- Authenticated app footer — compact single bar: a short compliance line plus
     copyright and the key links (Help, Terms, Privacy, Status). --}}
@php
    $legal = route('page.show', 'legal');
    $links = [
        [__('Help'), route('faqs.public')],
        [__('Terms'), $legal.'#terms'],
        [__('Privacy'), $legal.'#privacy'],
        [__('Status'), route('status')],
    ];
@endphp
<footer class="border-t border-slate-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
        <div class="flex flex-col items-center gap-3 text-xs text-slate-500 sm:flex-row sm:justify-between">
            <p>© {{ date('Y') }} PoisaPay. {{ __('All rights reserved.') }}</p>
            <nav class="flex flex-wrap items-center justify-center gap-x-5 gap-y-1">
                @foreach ($links as $link)
                    <a href="{{ $link[1] }}" class="transition hover:text-slate-900">{{ $link[0] }}</a>
                @endforeach
            </nav>
        </div>
    </div>
</footer>
