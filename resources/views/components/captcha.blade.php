@props(['feature', 'theme' => 'light'])
@php $captcha = app(\App\Support\Captcha\CaptchaService::class); @endphp
@if ($captcha->enabledFor($feature))
    @php
        $provider = $captcha->provider();
        $site = $captcha->siteKey();
    @endphp

    @if ($provider === \App\Support\Captcha\CaptchaService::V2_CHECKBOX)
        {{-- v2 checkbox — Google injects the hidden g-recaptcha-response field into the form. --}}
        <div class="g-recaptcha my-2" data-sitekey="{{ $site }}" data-theme="{{ $theme }}"></div>
        <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    @else
        {{-- v3 / invisible v2 — token injected into this hidden field on submit. --}}
        <input type="hidden" name="g-recaptcha-response" value="">

        @if ($provider === \App\Support\Captcha\CaptchaService::V3)
            <script src="https://www.google.com/recaptcha/api.js?render={{ $site }}"></script>
            <script>
                (function () {
                    var el = document.currentScript.parentElement;
                    var input = el.querySelector('input[name="g-recaptcha-response"]');
                    var form = input.closest('form');
                    if (!form) return;
                    form.addEventListener('submit', function (e) {
                        if (input.value) return;               // already have a token
                        e.preventDefault();
                        grecaptcha.ready(function () {
                            grecaptcha.execute('{{ $site }}', { action: '{{ $feature }}' })
                                .then(function (t) { input.value = t; form.submit(); });
                        });
                    });
                })();
            </script>
        @else
            {{-- v2 invisible --}}
            <div class="g-recaptcha" data-sitekey="{{ $site }}" data-size="invisible" data-badge="bottomright"
                 data-callback="__captchaDone_{{ $feature }}"></div>
            <script src="https://www.google.com/recaptcha/api.js" async defer></script>
            <script>
                (function () {
                    var el = document.currentScript.parentElement;
                    var input = el.querySelector('input[name="g-recaptcha-response"]');
                    var form = input.closest('form');
                    window['__captchaDone_{{ $feature }}'] = function (token) { input.value = token; if (form) form.submit(); };
                    if (form) form.addEventListener('submit', function (e) {
                        if (input.value) return;
                        e.preventDefault();
                        grecaptcha.execute();
                    });
                })();
            </script>
        @endif
    @endif

    @error('g-recaptcha-response')
        <p class="mt-1 text-sm text-rose-600">{{ $message }}</p>
    @enderror
@endif
