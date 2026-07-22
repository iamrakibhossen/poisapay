<x-layouts.public title="FAQs">
    <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6">
        <div class="mb-8 text-center">
            <h1 class="text-3xl font-bold text-neutral-900">Frequently asked questions</h1>
            <p class="mt-2 text-neutral-600">Everything you need to know about using PoisaPay.</p>
        </div>

        @forelse ($groups as $group => $faqs)
            <section class="mb-8">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-brand-700">{{ $group }}</h2>
                <div class="divide-y divide-neutral-200 overflow-hidden rounded-xl border border-neutral-200 bg-white">
                    @foreach ($faqs as $faq)
                        <div x-data="{ open: false }">
                            <button
                                type="button"
                                @click="open = ! open"
                                class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left hover:bg-neutral-50">
                                <span class="font-medium text-neutral-900">{{ $faq->question }}</span>
                                <x-heroicon-o-chevron-down class="h-5 w-5 shrink-0 text-neutral-400 transition" ::class="{ 'rotate-180': open }" />
                            </button>
                            <div
                                x-show="open"
                                x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0"
                                x-transition:enter-end="opacity-100"
                                class="px-5 pb-4 text-sm leading-relaxed text-neutral-700">
                                {{ $faq->answer }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @empty
            <div class="rounded-xl border border-neutral-200 bg-white p-10 text-center text-neutral-500">
                No FAQs published yet. Please check back soon.
            </div>
        @endforelse

        <div class="mt-10 text-center text-sm text-neutral-500">
            Still have questions? <a href="{{ route('home') }}" class="font-medium text-brand-700 underline">Contact our team</a>.
        </div>
    </div>
</x-layouts.public>
