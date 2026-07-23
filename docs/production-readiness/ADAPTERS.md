# PoisaPay — Provider Adapter Seams

Every external integration resolves through a **driver key** in `config/providers.php`,
so swapping a stub for a real vendor is one line + an env var — no call-site changes.
Defaults are built-in stubs, so the platform runs fully offline (and in CI).

## The registry — `config/providers.php`

| Seam | Interface | Default driver | Env |
|---|---|---|---|
| FX / crypto rates | `Exchange\Contracts\RateProvider` | `stub` (`StubRateProvider`) | `RATES_DRIVER`, `RATES_CACHE_TTL` |
| Sanctions/PEP/AML screening | `Compliance\Contracts\ScreeningProvider` | `stub` (`StubScreeningProvider`) | `SCREENING_DRIVER` |
| KYC / identity verification | `Kyc\Contracts\KycProvider` | `manual` (`ManualKycProvider`) | `KYC_DRIVER` |
| Fiat payout (off-ramp PSP) | `Ramp\Contracts\PayoutProcessor` | `stub` (`StubPayoutProcessor`) | `PAYOUT_DRIVER`, `PAYOUT_WEBHOOK_SECRET` |
| SMS transport | `Notification\Contracts\NotificationTransport` | `log` (`LogSmsTransport`) | `SMS_DRIVER` |
| Push transport | `Notification\Contracts\NotificationTransport` | `log` (`LogPushTransport`) | `PUSH_DRIVER` |

Bindings are wired in `AppServiceProvider::register()` via a small `bindDriver()` helper
(rates additionally wrap the configured feed in `CachingRateProvider`). Custody
(`AddressDeriver`, `SignerKeyProvider`) uses the same philosophy and is covered in the
custody roadmap (Wave 1).

## Adding a real provider (example: ComplyAdvantage screening)

1. Implement the interface:
   ```php
   final class ComplyAdvantageProvider implements ScreeningProvider {
       public function name(): string { return 'complyadvantage'; }
       public function evaluate(User $user): ScreeningOutcome { /* call API, map to ScreeningOutcome */ }
   }
   ```
2. Register the driver in `config/providers.php`:
   ```php
   'screening' => ['drivers' => ['complyadvantage' => ComplyAdvantageProvider::class, ...]],
   ```
3. Flip the env: `SCREENING_DRIVER=complyadvantage`.

That's it — `ScreeningService`, `TransactionMonitor`, and the onboarding listener are
untouched. The stub stays as the CI/test binding.

## Off-ramp (crypto → fiat) flow

New in this wave — the mirror of the existing on-ramp, using the same ledger primitives.

```
RequestOffRampAction        reserve: user:available -> user:locked   (RampStatus::Pending)
   -> PayoutProcessor::initiatePayout()                             (RampStatus::Confirmed, provider_ref set)
PSP webhook  POST /api/ramp/payout/webhook/{driver}
   -> PayoutProcessor::verifyWebhook() + parseWebhook()
   -> SettleOffRampAction::settle()   success: user:locked -> ramp:clearing   (RampStatus::Credited)
   -> SettleOffRampAction::fail()     failure: user:locked -> user:available  (RampStatus::Failed)
```

- **Reserve-before-send**: funds are locked in the ledger *before* the PSP is instructed,
  so a failed payout simply releases the hold — nothing can leak (same guarantee as
  on-chain withdrawals).
- **Idempotent** on the client key (`ramp_orders.idempotency_key`, new nullable-unique
  column) and on webhook retries (settle/fail short-circuit on terminal status).
- **Provider-agnostic webhook**: the PSP verifies its own signature and normalises its
  payload into a neutral `PayoutWebhookEvent`; the controller only correlates by
  `provider_ref` and applies the outcome.

## Notification transports

`NotificationService` delivers in-app + email via native Laravel channels (unchanged).
SMS and push now route through `NotificationTransportManager`, which resolves the
configured transport per channel. Stubs log; a real transport (Twilio, FCM) drops in via
config. Delivery still honours the user's per-category preferences and the template's
targeted channels, so default behaviour is identical until a template opts into sms/push.

## Tests

- `tests/Feature/OffRampTest.php` — reserve, settle, fail, HTTP webhook, idempotency, balance guard.
- `tests/Feature/ProviderSeamsTest.php` — each interface resolves to its configured stub; rate provider is the caching decorator; driver swap via config.

## What still needs the vendor side (not buildable without accounts)
The seams are complete and swappable; these require the customer's provider accounts to go live:
live rate feed (CoinGecko/Binance), real screening (ComplyAdvantage/Refinitiv), KYC
doc/liveness (Onfido/SumSub), fiat PSP (Wise/Flutterwave), SMS/push (Twilio/FCM). Each is a
new driver class + env flip — tracked in `CHECKLIST.md`.
