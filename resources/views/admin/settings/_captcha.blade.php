@php
    $active = (array) getSetting('captcha_features', []);
    $provider = old('captcha_provider', getSetting('captcha_provider', 'v2_checkbox'));
@endphp
<x-admin.form-layout :title="__('Google reCAPTCHA')" :description="__('Protect forms with Google reCAPTCHA. Fully optional — when off, everything works exactly as before. Keys are stored here, never in code.')" class="!my-0">
    <form class="space-y-5" method="POST" action="{{ route('admin.settings.update', 'captcha') }}">
        @csrf
        @method('PUT')

        {{-- General --}}
        <x-admin.input.group id="captcha_enabled" :label="__('Enable reCAPTCHA')" class="w-full" :hints="__('Master switch. Also requires a Site Key + Secret Key below.')">
            <x-admin.input.boolean name="captcha_enabled" :value="old('captcha_enabled', getSetting('captcha_enabled', false))" />
        </x-admin.input.group>

        <x-admin.input.group id="captcha_provider" :label="__('Provider')" required class="w-full" :hints="__('v2 Checkbox = the “I’m not a robot” box · v2 Invisible = runs on submit · v3 = scored, no interaction.')">
            <x-admin.input.select name="captcha_provider">
                <option value="v2_checkbox" @selected($provider === 'v2_checkbox')>{{ __('reCAPTCHA v2 — Checkbox') }}</option>
                <option value="v2_invisible" @selected($provider === 'v2_invisible')>{{ __('reCAPTCHA v2 — Invisible') }}</option>
                <option value="v3" @selected($provider === 'v3')>{{ __('reCAPTCHA v3 — Score') }}</option>
            </x-admin.input.select>
        </x-admin.input.group>

        <x-admin.input.group id="captcha_site_key" :label="__('Site Key')" class="w-full" :hints="__('Public key — rendered in the browser.')">
            <x-admin.input name="captcha_site_key" :value="old('captcha_site_key', getSetting('captcha_site_key', ''))" autocomplete="off" />
        </x-admin.input.group>

        <x-admin.input.group id="captcha_secret_key" :label="__('Secret Key')" class="w-full" :hints="__('Private key — used only server-side for verification. Never exposed.')">
            <x-admin.input type="password" name="captcha_secret_key" :value="old('captcha_secret_key', getSetting('captcha_secret_key', ''))" autocomplete="off" />
        </x-admin.input.group>

        <x-admin.input.group id="captcha_min_score" :label="__('Minimum Score (v3 only)')" required class="w-full" :hints="__('v3 returns 0.0–1.0; requests below this score are rejected. 0.5 is a sensible default.')">
            <x-admin.input type="number" step="0.1" min="0.1" max="1.0" name="captcha_min_score" :value="old('captcha_min_score', getSetting('captcha_min_score', 0.5))" required />
        </x-admin.input.group>

        {{-- Per-feature toggles (generated from config/captcha.php) --}}
        <div class="border-t border-gray-200 pt-5">
            <p class="text-sm font-semibold text-neutral-900">{{ __('Protected features') }}</p>
            <p class="text-xs text-neutral-500">{{ __('Enable reCAPTCHA independently per feature. Adding a new feature to config/captcha.php makes it appear here automatically.') }}</p>
        </div>

        @foreach (config('captcha.features', []) as $group => $features)
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wider text-neutral-400">{{ $group }}</p>
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($features as $key => $label)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm text-neutral-700 transition hover:border-gray-300 has-[:checked]:border-brand-400 has-[:checked]:bg-brand-50/60">
                            <input type="checkbox" name="captcha_features[]" value="{{ $key }}" @checked(in_array($key, $active, true))
                                class="h-4 w-4 rounded border-neutral-300 text-brand-600 focus:ring-brand-500">
                            <span>{{ __($label) }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach

        <div class="text-right">
            <x-admin.button type="submit">{{ __('Update') }}</x-admin.button>
        </div>
    </form>
</x-admin.form-layout>
