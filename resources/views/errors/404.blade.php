<x-layouts.marketing :title="__('Page not found')" :description="__('The page you were looking for could not be found.')">
    <div class="mx-auto flex min-h-[70vh] max-w-lg flex-col items-center justify-center px-4 py-32 text-center sm:py-40">
        <p class="grad-text text-8xl font-black leading-none tracking-tight sm:text-9xl">404</p>

        <h1 class="mt-5 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl">{{ __('Page not found') }}</h1>
        <p class="mx-auto mt-3 max-w-sm text-slate-600">
            {{ __("The page you're looking for doesn't exist or has moved.") }}
        </p>

        <a href="{{ route('home') }}" class="pp-btn pp-btn-primary pp-btn-sm mt-8">
            <x-heroicon-o-home class="h-4 w-4" /> {{ __('Back to home') }}
        </a>
    </div>
</x-layouts.marketing>
