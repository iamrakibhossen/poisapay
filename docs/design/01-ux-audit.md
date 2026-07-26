# PoisaPay — Phase 1: UX Audit

**Scope:** Consumer frontend (server-rendered Blade + Alpine, Tailwind v4, `body.theme-minimal` blue/slate/Nunito theme, Reverb realtime). Grounded in a code-level inventory of ~58 Blade views, the 39-component `x-ui` kit, `resources/css/app.css` tokens, and the IA (`components/partials/sidebar.blade.php`, `topbar.blade.php`).

**Method:** Every finding below is tied to a real screen/file. Severity reflects impact on the stated product goal — *"users should never feel confused; every action instant, predictable, secure, professional"* — weighted by traffic and money/trust sensitivity.

**Severity rubric**
- **Critical** — blocks a core task, creates money/trust uncertainty, or structurally caps the quality bar.
- **High** — significant friction/confusion on a high-traffic or conversion/trust-critical flow.
- **Medium** — noticeable friction, inconsistency, or a scale/maintainability risk.
- **Low** — minor or cosmetic.

---

## 0. What is already strong (do NOT rebuild)

A senior audit starts by protecting what works, because the anti-goal is a cosmetic rewrite that regresses proven flows.

- **A real component system.** 39 `x-ui` components with locked form-control heights, semantic color scale (success/warning/danger/info), focus-visible rings, disabled states, and reduced-motion support. This is well above seed-stage.
- **Consistent money-form pattern.** Hero amount field + asset pill + balance/MAX chip + live summary (Send, Withdraw, Exchange) is genuinely good and should become the canonical pattern, not be replaced.
- **Consistent empty states.** `x-ui.empty-state` is used across dashboard, wallet, send, deposit, withdraw, cards, history, P2P, rewards — the "no empty dead-ends" rule is *already substantially met*.
- **Escrow/P2P depth, live chat (Reverb), quote-based exchange with countdown** — trust primitives most competitors lack.
- **Token foundation** in `app.css` (`--color-brand-*`, `--shadow-card/pop`, semantic buttons via CSS vars) — extend it, don't discard it.

The redesign is therefore **evolution of a solid base**, not a teardown. That is the correct posture for a money app: never regress a working money flow for visual novelty.

---

## STRATEGIC FINDING (read first)

**STR-1 — The quality bar you specified is architecturally gated. [Critical / Decision required]**

The mandate lists: optimistic UI, page transitions <200ms, offline cache, background sync, app launch <2s, and "everything updates automatically, no manual refresh." The current architecture (per `CLAUDE.md`) is **server-rendered Blade MVC with form POST → full-page redirect, no JSON API, no Livewire**, Alpine for light interactivity, and realtime wired to **only** the P2P chat channel. The dashboard simulates "live" via a 60-second `/dashboard/live` poll.

Full-page reloads and 60s polling **cannot** produce Apple-Wallet/Linear-grade instant, optimistic, offline-capable UX. This is not a screen-level bug; it is the ceiling on how "instant and alive" the product can feel. Every later phase (hi-fi, motion, implementation) depends on resolving it. The realistic options:

| Option | What it means | Instant/optimistic/offline | Effort / risk | Keeps Blade investment |
|---|---|---|---|---|
| **A. Enhance-in-place** | Add Turbo/Hotwire-style partial swaps + broad skeletons + broaden Reverb to balances/notifications; keep Blade MVC | Good (fast partial nav, live pushes) — not true offline | Low–Med | Yes (highest reuse) |
| **B. PWA app-shell** | Wrap the app as an installable PWA with a client shell, service-worker cache/background sync, optimistic writes over a thin API | Excellent (offline, background sync, app-launch feel) | High | Partial (needs an API layer) |
| **C. Livewire/Inertia** | Reintroduce a reactive layer for SPA-like transitions | Very good | Med–High (contradicts current "no Livewire" stance) | Partial |

**This is your decision, and it must be made before Phase 5.** My recommendation for a 100M-scale emerging-market app: **A now, B next** — enhance-in-place to hit "fast and alive" cheaply and without a rewrite, then layer a PWA shell for offline/background-sync where the emerging-market network reality (intermittent connectivity) makes it a genuine differentiator. Design the system so the visual/interaction layer is identical under either — so this decision doesn't block Phases 2–4.

---

## 1. Navigation & Information Architecture

| ID | Finding | Why it's a problem | Severity |
|---|---|---|---|
| N1 | **No global search anywhere.** Absent from topbar/sidebar; confirmed missing across consumer views. | A super-app spanning wallet, P2P, cards, orders, merchants, transactions, FAQ has no way to jump to an entity. Sidebar-only nav collapses as features grow; at 100M users and dozens of surfaces, "find my order / that merchant / this asset" becomes impossible. Violates the super-app navigation model. | **High** |
| N2 | **Mobile nav is an off-canvas hamburger drawer**, not a persistent bottom tab bar. Primary destinations are 2 taps away (open drawer → tap). | Money apps are used one-handed on phones. Every top competitor (Cash App, Revolut, Wise, RedotPay) uses a thumb-reachable bottom tab bar for the 4–5 core destinations. Hamburger-first nav adds a tap to *every* navigation and hides the app's breadth. | **High** |
| N3 | **No breadcrumb / consistent back affordance** on deep pages (asset detail, card manage, order). | Users lose their place in nested flows; violates "never feel confused." | Medium |
| N4 | **Feature-gated items appear/disappear** (P2P, Shop) based on flags/eligibility with no "discover" surface. | New capabilities are invisible until enabled; hurts cross-sell of the super-app. | Medium |

---

## 2. Home Dashboard

Strong overall (balance hero, quick actions, assets, card, recent activity). Gaps:

| ID | Finding | Why | Severity |
|---|---|---|---|
| D1 | **60s poll, not push** for balance/portfolio. | Balance can be stale up to a minute after a deposit/trade — in a money app this reads as "did it work?" Violates "everything updates automatically." | **High** |
| D2 | **No 24h change** per asset on the dashboard. | Users can't gauge portfolio movement at a glance — a baseline fintech expectation. | Medium |
| D3 | **No promotions / market-summary / rewards module** on home (listed as desired). | Home under-monetizes attention and misses cross-sell/engagement surface. | Medium |
| D4 | Quick actions are 5–6 pills; no personalization/reordering by usage. | Misses "smart defaults" — frequent actions aren't surfaced first. | Low |

---

## 3. Wallet & Assets

| ID | Finding | Why | Severity |
|---|---|---|---|
| W1 | **No 24h change %** in the asset list (`wallet.blade.php`). | The list can't answer "is my money up or down?" — the primary question a wallet list should answer. | Medium |
| W2 | **No hide-zero-balances toggle and no sort options.** | As asset count grows the list gets noisy; violates the wallet spec (hide zero, sort). | Medium |
| W3 | No portfolio allocation visualization. | Minor; helps comprehension at a glance. | Low |

---

## 4. Money Movement (Deposit / Withdraw / Send / Exchange)

| ID | Finding | Why | Severity |
|---|---|---|---|
| M1 | **Withdraw is 4–5 taps; Deposit 3–4.** | Violates the mandatory "max 3 taps for core actions." Withdraw especially (asset → network/account → amount → address/2FA → submit) is long for a frequent action. | **High** |
| M2 | **Money submits are full-page POST→reload with a disabled-button state — no skeleton, no optimistic pending state.** | For *money* actions, the gap between submit and reload is exactly where users get anxious ("did my withdrawal go through? should I click again?"). Risk of double-submit and eroded trust. Violates feedback + skeleton rules. | **Critical** |
| M3 | **Send has no recipient history / favorites quick-select.** | Users retype IDs/emails every time; violates "smart defaults / reduce typing," a top friction point in P2P sends. | Medium |
| M4 | **Large/irreversible crypto withdrawals get a summary box but no explicit review step or whitelist/cooldown affordance.** | Irreversible money movement should force a deliberate confirm; current flow can submit from the same screen. Trust/safety. | Medium |
| M5 | Exchange expired-quote recovery is a manual "refresh quote" link; no auto-refresh option. | Minor friction on a good flow. | Low |

---

## 5. Virtual Cards

Already strong (visual card, freeze, CVV reveal with step-up, PIN, limits, history, disputes, spend analytics).

| ID | Finding | Why | Severity |
|---|---|---|---|
| CA1 | **Spending controls (online / ATM / contactless) exist as hidden inputs, not visible toggles.** | A key security/control affordance users expect (and that builds trust) is not surfaced. | Medium |
| CA2 | Reveal-details uses a modal with "Loading…" text, not a skeleton. | Minor inconsistency with the skeleton rule. | Low |
| CA3 | No geo/MCC/velocity control UI (referenced in mandate). | Power-user control gap; progressive-disclosure candidate. | Low |

---

## 6. Checkout (conversion-critical)

`funnel/pay.blade.php` is a real one-page checkout, but has conversion leaks:

| ID | Finding | Why | Severity |
|---|---|---|---|
| CK1 | **Login-only (no guest checkout).** | First-time buyers must create an account to pay — the single largest checkout conversion killer. | **High** |
| CK2 | **~10–15 fields when shipping is required; saved address not reused.** | Violates "very few fields." Every field is measurable drop-off. | **High** |
| CK3 | **Coupon applies via GET page reload, not inline validation.** | The reload feels broken/slow at the highest-intent moment; no inline success/fail feedback. Violates feedback rule. | Medium |
| CK4 | **Wallet is the only payment method at point of sale.** | No card/mobile-money fallback for buyers without a funded wallet — lost sales. | Medium |
| CK5 | No progress/step indicator when shipping is present. | Minor orientation gap. | Low |

---

## 7. Merchant / Seller

Broad and modern (14 screens: products, orders, earnings, analytics, customers, coupons, reviews, funnels, domains, inbox). Gaps:

| ID | Finding | Why | Severity |
|---|---|---|---|
| MS1 | **No seller-facing refund-request management UI.** | Refunds are referenced but sellers can't action them — an operational dead-end. | Medium |
| MS2 | **No payout history list** (only available/pending balances). | Sellers can't reconcile payouts; trust/clarity gap. | Medium |
| MS3 | Analytics pixels marked "coming soon"; no CSV export/bulk actions. | Power-seller scale gaps. | Low |

---

## 8. Settings

| ID | Finding | Why | Severity |
|---|---|---|---|
| S1 | **No Appearance / theme (dark mode) setting.** | Dark mode is a mandated deliverable and a baseline expectation; there's no user control (and no dark tokens — see DS1). | **High** |
| S2 | **Language selector lives only in the topbar, not in Settings.** | Users look for language in Settings; discoverability gap. | Low |
| S3 | Full-page reload per tab; no unsaved-changes guard. | Minor friction; risk of lost edits. | Medium |
| S4 | **No account deletion / data export.** | Privacy-regulation expectation (GDPR-style) missing. | Medium |

---

## 9. Notifications & 10. Search & 11. KYC & 12. Rewards

| ID | Finding | Why | Severity |
|---|---|---|---|
| NT1 | Notifications grouped by date + category filter + unread — **good.** But **no push/SMS channel** (in-app + email only). | "Everything updates automatically" implies push; without it, time-sensitive events (order paid, dispute) are missed off-app. | **High** |
| NT2 | No notification search / archive / priority levels. | Scale gaps for heavy users. | Low |
| SR1 | Global search missing — see **N1**. | — | (High) |
| K1 | KYC 3-step wizard with stepper is solid, but **no liveness/selfie** step. | Weakens fraud defense and compliance completeness for a money platform. | Medium |
| K2 | Rejection→resubmit flow is thin (no clear reason surfacing/guidance). | Users stuck after rejection = lost activation. | Medium |
| RW1 | Rewards is referral-centric; no reward breakdown/redemption/expiry clarity. | Engagement/clarity gap. | Low |

---

## 13. Design System (visual & tokens)

| ID | Finding | Why | Severity |
|---|---|---|---|
| DS1 | **Dark mode variant (`@custom-variant dark`) is defined but unwired — no dark token set, no toggle. App is light-only.** | Mandated deliverable; also an emerging-market battery/OLED and accessibility expectation. | **High** |
| DS2 | **Skeleton component exists but is barely used; loading defaults to spinners + full reloads.** | Directly violates the "skeletons, not spinners" rule; makes the app feel slower than it is. | **High** |
| DS3 | **No formal elevation/shadow scale** (2 shadow tokens; components use ad-hoc `shadow-sm/lg/2xl` + inline). | Inconsistent depth; hard to scale a premium, coherent surface system. | Medium |
| DS4 | **No radius scale** (single `--radius-card`; components hardcode `rounded-lg/xl/2xl`). | Visual inconsistency; not token-driven. | Medium |
| DS5 | **No motion tokens / easing scale** (durations hardcoded 150/200/250/4000ms). | The mandated 150–200ms motion language isn't systematized; inconsistent feel. | Medium |
| DS6 | **No illustration system** — empty/success/error use icon-in-a-circle only. | Premium fintechs use a small illustration set to build warmth/trust in empty & success moments. | Medium |
| DS7 | **No z-index scale** (arbitrary z-10…z-[70]). | Stacking bugs as overlays multiply. | Low |
| DS8 | **No design-system source of truth / documentation** (no tokens.json, no usage doc). | Onboarding tax + drift as the team scales toward 100M-scale delivery. | Medium |

---

## 14. Feedback, Motion & Performance (cross-cutting)

| ID | Finding | Why | Severity |
|---|---|---|---|
| F1 | **Realtime is wired to P2P chat only.** Balances, activity, notifications, orders do not push; no websocket connection-status indicator. | Violates "everything updates automatically." The infrastructure (Reverb) exists but is under-adopted. | **High** |
| F2 | **No page transitions** (<200ms) — full reloads between screens. | Reads as slower/older than the target bar. (Gated by STR-1.) | Medium |
| F3 | **No optimistic UI, offline cache, or background sync.** | Mandated; unattainable on the current stack (STR-1). Critical in intermittent-connectivity emerging markets. | Medium (→ Critical if targeting the full mandate) |

---

## 15. Accessibility (WCAG / mandate rule 10)

| ID | Finding | Why | Severity |
|---|---|---|---|
| A1 | **No `aria-live` on toasts/alerts.** | Screen-reader users miss async feedback (deposit credited, error). | Medium |
| A2 | **Form errors not linked via `aria-describedby`.** | Errors are visible but not announced/associated. | Low |
| A3 | **Some neutral text (gray-500 on white) is borderline contrast.** | Readability for low-vision users and in sunlight (emerging-market outdoor use). | Medium |
| A4 | **Small icon-only buttons (~24px) fall below the 44px touch target.** | Mis-taps on money controls; violates rule 10. | Medium |

---

## Severity Heatmap (all findings)

| Severity | Count | IDs |
|---|---:|---|
| **Critical** | 2 | STR-1, M2 |
| **High** | 10 | N1, N2, D1, M1, CK1, CK2, S1, NT1, DS1, DS2, F1 *(11 — F1 included)* |
| **Medium** | 18 | N3, N4, D2, D3, W1, W2, M3, M4, CA1, CK3, CK4, MS1, MS2, S3, S4, K1, K2, DS3, DS4, DS5, DS6, DS8, F3, A1, A3, A4 |
| **Low** | 14 | D4, W3, M5, CA2, CA3, CK5, MS3, S2, NT2, RW1, DS7, F2, A2 |

*(Counts approximate; the two true blockers are STR-1 and M2 — resolve those first.)*

---

## The five systemic themes (what to actually fix)

Rather than 45 point-fixes, the audit collapses into **five systemic root causes**. Fixing these resolves most findings:

1. **Liveness gap** (D1, F1, F3, NT1, M2). Reverb exists but only P2P uses it. Systematize: push balances, activity, notifications, order status on user channels; add optimistic pending states + broad skeletons; add push notifications. → makes the app feel *alive and trustworthy*.

2. **Navigation for a super-app** (N1, N2, N3, N4). Add global search + a mobile bottom tab bar + consistent back/breadcrumb. → makes 12 surfaces feel like *one predictable ecosystem*.

3. **Tap-count & friction on money & checkout** (M1, M3, CK1, CK2, CK3). Collapse withdraw to ≤3 taps, add recipient/address memory, add guest checkout + inline coupons + saved addresses. → directly moves *conversion*.

4. **Formalize the design system** (DS1–DS8). Ship a real token layer (color incl. **dark mode**, elevation, radius, motion, z-index), an illustration set, and docs. → *consistency + premium feel + team scale*.

5. **Accessibility & control polish** (CA1, S1, S4, A1–A4, K1). Surface hidden controls, add appearance/theme + language in settings, aria-live/describedby, 44px targets, liveness. → *trust, compliance, inclusivity*.

---

## Phase gate

Phase 1 is complete. Phases 2–7 (IA → design system → lo-fi → hi-fi → interactive flows → implementation) should proceed **only after two decisions**, because they change the design direction materially:

1. **Delivery architecture** (STR-1) — enhance-in-place vs PWA app-shell vs Livewire/Inertia. Recommended: enhance-in-place now, PWA next.
2. **Cadence** — phase-by-phase approval, or proceed through the design phases (2–4) and check in before hi-fi.
