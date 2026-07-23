# PoisaPay — Notifications & Support UI (Wave 6)

## Notification transports (real vendors behind the Wave-3 seam)
Every channel resolves through `NotificationTransportManager` from
`config/providers.php` → `notifications`; the vendor is a one-line driver swap.

| Channel | Default driver | Real driver | Destination |
|---|---|---|---|
| SMS | `log` | `TwilioSmsTransport` (`SMS_DRIVER=twilio`) | `users.phone` |
| Push | `log` | `FcmPushTransport` (`PUSH_DRIVER=fcm`) | `user_push_tokens` (FCM/APNs) |
| WhatsApp | `twilio` (no-op until configured) | `WhatsappTransport` | `whatsapp:<users.phone>` |
| Telegram | `telegram` (no-op until configured) | `TelegramTransport` | `users.telegram_chat_id` |

- Credentials live in `config/services.php` (`twilio`, `fcm`, `telegram`); all transports
  **no-op safely** when unconfigured or when the user has no destination, so they never
  throw mid-notification.
- `NotificationService` now fans to all four transport channels, gated by the user's
  per-category `NotificationPreference` (new `whatsapp` / `telegram` columns, default off)
  intersected with the template's targeted channels — so default behaviour is unchanged
  until a template opts a category into a new channel.
- **Push-token enrollment:** `POST /api/v1/push-tokens {token, platform}` (Sanctum) /
  `DELETE /api/v1/push-tokens`.
- Tested with `Http::fake` (`tests/Feature/NotificationTransportsTest.php`) — asserts the
  exact outbound request shape for Twilio SMS, FCM, WhatsApp, and Telegram, plus the safe
  no-op when unconfigured.

## Support tickets (new — models existed, zero UI before)
- **Domain:** `SupportTicketService` — open (initial message + operator alert), user reply
  (re-opens), staff reply (→ pending + notifies the user), status, assign. Staff replies
  aren't tied to a user row (`author_id` nullable + `author_name`; migration additive).
- **Status:** `SupportTicketStatus` enum (open / pending / resolved / closed).
- **User UI:** `/support` (list), `/support/new` (create), `/support/{id}` (threaded view +
  reply). Nav link under Account.
- **Admin UI:** `/admin/support` (filterable queue), `/admin/support/{id}` (thread + staff
  reply + status/assign). Nav link under Commerce. Gated by new `view-support` /
  `manage-support` permissions (granted to `admin` + `support` roles).
- Tested end-to-end (`tests/Feature/SupportTicketTest.php`): open, ownership scoping,
  staff/user threading + status transitions, close-then-reject-reply.

## Feature-flag console (new)
- `/admin/feature-flags` — one page listing all togglable module/security/auth flags with
  live on/off toggles, writing the same settings engine `feature()` reads. Gated by the
  existing `manage-feature-flags` permission. Nav link under System.
- Tested (`tests/Feature/Wave6MiscTest.php`): render, toggle, unknown-flag rejection.

## Already present (verified during the map — not rebuilt)
- **Referral sharing/tracking UI** — `frontend/rewards.blade.php` already renders the
  referral code, share link, and referral list (`RewardsController`).
- **Card dispute resolution** — `ResolveCardDisputeAction` + `admin/card-disputes` already
  exist (won/lost adjudication with chargeback ledger posting).

## Note on the parallel P2P wave
A separate agent is concurrently building the P2P marketplace in the same repo. Its
`database/migrations/2026_07_23_000000_create_p2p_tables.php` is under active edit and can
transiently break the shared test-suite `RefreshDatabase` (the full-run failure count varied
2→9 between identical runs while all Wave 6 tests and the affected admin tests pass in
isolation). Re-run the suite once the P2P migration stabilises. Wave 6 touched **no** P2P files.
