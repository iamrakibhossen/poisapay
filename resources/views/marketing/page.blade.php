<x-layouts.marketing :title="$page->title" :description="$page->meta_description">
    <div class="mx-auto max-w-3xl px-4 pb-24 pt-6 sm:px-6">
        <article class="glass-card p-8 sm:p-10
            [&_h2]:mt-8 [&_h2]:mb-3 [&_h2]:scroll-mt-24 [&_h2]:text-xl [&_h2]:font-bold [&_h2]:text-slate-900
            [&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:text-lg [&_h3]:font-semibold [&_h3]:text-slate-900
            [&_p]:mb-4 [&_p]:leading-relaxed [&_p]:text-slate-600
            [&_ul]:mb-4 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:text-slate-600
            [&_ol]:mb-4 [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:text-slate-600
            [&_li]:mb-1
            [&_a]:font-medium [&_a]:text-brand-700 [&_a]:underline
            [&_strong]:font-semibold [&_strong]:text-slate-900">
            <h1 class="mb-6 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">{{ $page->title }}</h1>
            {!! $page->content !!}
        </article>
    </div>
</x-layouts.marketing>
