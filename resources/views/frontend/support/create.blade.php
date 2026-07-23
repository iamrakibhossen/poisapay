<x-layouts.app :title="__('New ticket')">
    @php
        $catMeta = [
            'general' => 'chat-bubble-left-right',
            'account' => 'user-circle',
            'deposit' => 'arrow-down-tray',
            'withdrawal' => 'arrow-up-tray',
            'card' => 'credit-card',
            'kyc' => 'identification',
            'other' => 'ellipsis-horizontal-circle',
        ];
    @endphp

    <div class="mx-auto max-w-3xl space-y-5">
        <x-ui.page-header :title="__('New ticket')" :subtitle="__('Tell us what you need help with and our team will follow up.')">
            <x-slot:actions>
                <a href="{{ route('support.index') }}"><x-ui.button variant="secondary" icon="arrow-left">{{ __('Back to support') }}</x-ui.button></a>
            </x-slot:actions>
        </x-ui.page-header>

        @if ($errors->any())<x-ui.alert type="error">{{ $errors->first() }}</x-ui.alert>@endif

        <form method="POST" action="{{ route('support.store') }}"
              x-data="{ category: @js(old('category', 'general')), priority: @js(old('priority', 'normal')), body: @js(old('body', '')) }">
            @csrf
            <x-ui.card class="space-y-6">
                {{-- Subject --}}
                <x-ui.input name="subject" :label="__('Subject')" :value="old('subject')" maxlength="160"
                    placeholder="{{ __('Briefly, what is this about?') }}" :error="$errors->first('subject')" required />

                {{-- Category picker --}}
                <div>
                    <label class="pp-label">{{ __('Category') }}</label>
                    <input type="hidden" name="category" :value="category">
                    <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($categories as $c)
                            <button type="button" @click="category = @js($c)"
                                    :class="category === @js($c) ? 'border-brand-500 bg-brand-50 text-brand-700' : 'border-neutral-200 text-neutral-600 hover:border-neutral-300'"
                                    class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm font-medium transition-colors">
                                <x-dynamic-component :component="'heroicon-o-'.($catMeta[$c] ?? 'ellipsis-horizontal-circle')" class="h-4 w-4 shrink-0" />
                                {{ ucfirst($c) }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Priority --}}
                @php
                    $prios = [
                        'low' => ['label' => __('Low'), 'dot' => 'bg-green-500', 'active' => 'border-green-500 bg-green-50 text-green-700'],
                        'normal' => ['label' => __('Normal'), 'dot' => 'bg-brand-500', 'active' => 'border-brand-500 bg-brand-50 text-brand-700'],
                        'high' => ['label' => __('High'), 'dot' => 'bg-red-500', 'active' => 'border-red-500 bg-red-50 text-red-700'],
                    ];
                @endphp
                <div>
                    <label class="pp-label">{{ __('Priority') }}</label>
                    <input type="hidden" name="priority" :value="priority">
                    <div class="mt-1 grid grid-cols-3 gap-2">
                        @foreach ($prios as $p => $meta)
                            <button type="button" @click="priority = @js($p)"
                                    :class="priority === @js($p) ? '{{ $meta['active'] }}' : 'border-neutral-200 text-neutral-600 hover:border-neutral-300'"
                                    class="flex items-center justify-center gap-1.5 rounded-xl border px-3 py-2.5 text-sm font-medium transition-colors">
                                <span class="h-2 w-2 rounded-full {{ $meta['dot'] }}"></span>{{ $meta['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Body --}}
                <div>
                    <label class="pp-label">{{ __('How can we help?') }}</label>
                    <textarea name="body" x-model="body" required rows="6" maxlength="5000"
                              placeholder="{{ __('Share as much detail as you can — steps you took, amounts, dates, and any error messages.') }}"
                              class="pp-input"></textarea>
                    <p class="mt-1 text-right text-xs text-neutral-400"><span x-text="body.length"></span>/5000</p>
                    @error('body')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex items-center justify-between border-t border-neutral-100 pt-4">
                    <p class="hidden items-center gap-1.5 text-xs text-neutral-400 sm:flex">
                        <x-heroicon-o-shield-check class="h-4 w-4" /> {{ __('Never share your password or full card number.') }}
                    </p>
                    <x-ui.button type="submit" icon="paper-airplane">{{ __('Submit ticket') }}</x-ui.button>
                </div>
            </x-ui.card>
        </form>
    </div>
</x-layouts.app>
