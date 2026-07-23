<x-layouts.app :title="__('New ticket')">
    <div class="mx-auto max-w-2xl">
        <header class="mb-6">
            <a href="{{ route('support.index') }}" class="text-sm text-neutral-500 hover:text-neutral-900">{{ __('← Back to support') }}</a>
            <h1 class="mt-2 text-2xl font-semibold tracking-tight text-neutral-900">{{ __('New ticket') }}</h1>
        </header>

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('support.store') }}" class="space-y-4 rounded-2xl border border-neutral-200 bg-white p-6">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-neutral-700">{{ __('Subject') }}</label>
                <input name="subject" value="{{ old('subject') }}" required maxlength="160" class="w-full rounded-lg border-neutral-300 text-sm" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-neutral-700">{{ __('Category') }}</label>
                    <select name="category" class="w-full rounded-lg border-neutral-300 text-sm">
                        @foreach ($categories as $c)
                            <option value="{{ $c }}" @selected(old('category') === $c)>{{ ucfirst($c) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-neutral-700">{{ __('Priority') }}</label>
                    <select name="priority" class="w-full rounded-lg border-neutral-300 text-sm">
                        @foreach (['low', 'normal', 'high'] as $p)
                            <option value="{{ $p }}" @selected(old('priority', 'normal') === $p)>{{ __(ucfirst($p)) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-neutral-700">{{ __('How can we help?') }}</label>
                <textarea name="body" required rows="6" maxlength="5000" class="w-full rounded-lg border-neutral-300 text-sm">{{ old('body') }}</textarea>
            </div>
            <button class="rounded-lg bg-neutral-900 px-4 py-2 text-sm font-semibold text-white">{{ __('Submit ticket') }}</button>
        </form>
    </div>
</x-layouts.app>
