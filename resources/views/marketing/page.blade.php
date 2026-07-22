<x-layouts.public :title="$page->title">
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <article class="prose max-w-none
            [&_h1]:mb-4 [&_h1]:text-3xl [&_h1]:font-bold [&_h1]:text-neutral-900
            [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-neutral-900
            [&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-neutral-900
            [&_p]:mb-4 [&_p]:leading-relaxed [&_p]:text-neutral-700
            [&_ul]:mb-4 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:text-neutral-700
            [&_ol]:mb-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:text-neutral-700
            [&_li]:mb-1
            [&_a]:font-medium [&_a]:text-brand-700 [&_a]:underline
            [&_strong]:font-semibold [&_strong]:text-neutral-900">
            <h1 class="!mb-6 text-3xl font-bold text-neutral-900">{{ $page->title }}</h1>
            {!! $page->content !!}
        </article>
    </div>
</x-layouts.public>
