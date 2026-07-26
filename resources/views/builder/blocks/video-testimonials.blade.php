@php $items = $props['items'] ?? []; @endphp
@if (! empty($items))
    <section id="{{ $node->id }}" class="pp-block border-t border-neutral-100 py-14">
        <div class="mx-auto max-w-4xl px-5">
            @if (! empty($props['heading']))<h2 class="text-center text-2xl font-bold tracking-tight sm:text-3xl">{{ $props['heading'] }}</h2>@endif
            <div class="mt-8 grid gap-5 sm:grid-cols-2">
                @foreach ($items as $v)
                    @php $v = is_array($v) ? $v : ['url' => $v]; @endphp
                    <figure class="overflow-hidden rounded-2xl border border-neutral-200 bg-black">
                        @if (! empty($v['url']))
                            <div class="aspect-video"><iframe src="{{ $v['url'] }}" class="h-full w-full" loading="lazy" allowfullscreen title="{{ $v['name'] ?? 'testimonial' }}"></iframe></div>
                        @else
                            <div class="grid aspect-video place-items-center text-neutral-500"><x-heroicon-o-play-circle class="h-10 w-10" /></div>
                        @endif
                        @if (! empty($v['name']))<figcaption class="bg-white px-4 py-3 text-sm"><span class="font-semibold text-neutral-800">{{ $v['name'] }}</span>@if (! empty($v['role']))<span class="text-neutral-400"> · {{ $v['role'] }}</span>@endif</figcaption>@endif
                    </figure>
                @endforeach
            </div>
        </div>
    </section>
@endif
