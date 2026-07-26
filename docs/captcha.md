# Google reCAPTCHA

A self-contained, optional reCAPTCHA layer. **Nothing is hardcoded** — every key,
the provider, the v3 score, and per-feature toggles live in **Admin → Settings →
Google reCAPTCHA**. When disabled (the default), the app behaves exactly as before.

## Architecture

| Piece | Responsibility |
|-------|----------------|
| `config/captcha.php` | Feature **registry** (grouped `key => label`) + transport defaults. The only file you edit to add a new protectable feature. |
| `App\Support\Captcha\CaptchaService` | The single verification engine: `enabled()`, `enabledFor($feature)`, `verify($token, $feature, $ip)`. Reads settings, calls Google, enforces v3 score, blocks replays, rate-limits, logs, fails gracefully. |
| `App\Support\Captcha\Captcha` | Thin static entry point — `Captcha::verify(...)`, `Captcha::rule('login')`, `Captcha::enabledFor(...)`. |
| `App\Rules\CaptchaResponse` | Validation rule (passes when the feature is disabled). |
| `App\Http\Middleware\VerifyCaptcha` | Route guard: `->middleware('captcha:feature')`. |
| `<x-captcha :feature="'login'" />` | Renders the widget (v2 checkbox / v2 invisible / v3) **only when enabled** for that feature. |

Verification always happens **server-side**. The secret key is never sent to the browser.

## Configure (admin)

1. Get a Site Key + Secret Key from <https://www.google.com/recaptcha/admin>.
2. **Admin → Settings → Google reCAPTCHA**:
   - **Enable reCAPTCHA** → on (also requires both keys, or it stays inert).
   - **Provider** → v2 Checkbox / v2 Invisible / v3.
   - **Site Key** / **Secret Key**.
   - **Minimum Score** (v3 only, 0.1–1.0; 0.5 is a good start).
   - Tick the **features** you want protected.
3. Save — the settings cache clears automatically.

## Add reCAPTCHA to a new page (one config entry)

1. Add a row to `config/captcha.php` → `features`:
   ```php
   'Forms' => [
       // ...
       'my_new_form' => 'My New Form',
   ],
   ```
   It now appears as an admin toggle automatically.

2. Protect the submit — pick ONE:
   - **Validation rule** (controllers / form requests):
     ```php
     $request->validate([
         // ...
         'g-recaptcha-response' => \App\Support\Captcha\Captcha::rule('my_new_form'),
     ]);
     ```
   - **Middleware** (any route):
     ```php
     Route::post('/my-form', [MyController::class, 'store'])->middleware('captcha:my_new_form');
     ```

3. Render the widget in the form:
   ```blade
   <form method="POST" action="...">
       @csrf
       {{-- fields --}}
       <x-captcha feature="my_new_form" />
       <button type="submit">Submit</button>
   </form>
   ```

That's it — no business-logic changes.

## Enable / disable per feature

Toggle each feature independently in the admin settings. Turning a feature off
makes its `Captcha::rule(...)`/middleware/component no-ops instantly (settings are
cached and busted on save).

## Switch v2 ↔ v3

Just change **Provider** in admin (and Minimum Score for v3). The `<x-captcha>`
component renders the right widget and the service verifies accordingly — no code
changes. Remember v2 and v3 use **different keys**, so update Site/Secret Key too.

## Security & resilience

- **Server-side only**, secret never exposed.
- **Replay protection** — a verified token is single-use (cached for `replay_ttl`).
- **Rate limiting** — `rate_limit` verifications per IP per minute.
- **Graceful failure** — a Google timeout/outage **fails open** (logged) so users
  aren't locked out; an actual `success:false`, low v3 score, replay, or missing
  token **fails closed**.
- **Localised** error via `__('Captcha verification failed. Please try again.')`.

## Wired so far

Authentication: **Login, Register, Forgot Password, Reset Password**. Every other
feature in the registry (P2P, Shop, Merchant, Forms, API, Admin) has an admin
toggle ready — protect each by adding the rule/middleware + `<x-captcha>` to its
form (one line each, per the steps above). No business logic is touched.

Tests: `tests/Feature/Captcha/CaptchaTest.php`.
